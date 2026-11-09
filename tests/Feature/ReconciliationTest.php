<?php

namespace Tests\Feature;

use App\Application\Reconcile;
use App\Application\RefundOrder;
use App\Models\Product;
use App\Models\ReconciliationFinding;
use App\Models\SupplierStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesOrders;
use Tests\TestCase;

final class ReconciliationTest extends TestCase
{
    use CreatesOrders;
    use RefreshDatabase;

    public function test_statement_differences_converge_and_operations_review_is_audited(): void
    {
        $this->travelTo(now('Asia/Taipei')->startOfDay()->addHours(8));
        $operator = User::factory()->create(['role' => 'operator']);
        $product = Product::factory()->stocked(5)->create();
        $order = $this->place($product);
        $this->pay($order);
        $this->issue($order);
        $statement = SupplierStatement::where('order_reference', $order->reference)->firstOrFail();
        $statement->update(['quantity' => 2]);
        $orphan = SupplierStatement::create([
            'supplier_id' => $product->supplier_id, 'request_key' => (string) Str::uuid(),
            'order_reference' => (string) Str::uuid(), 'quantity' => 1, 'ready_at' => now(),
        ]);
        $refunds = app(RefundOrder::class);
        $refunds->review($operator, $refunds->request($operator, $order), true);
        $this->travel(31)->minutes();
        $this->actingAs($operator)->get('/ops/reconciliation')->assertOk()->assertSee('交易對帳');

        $service = app(Reconcile::class);
        $day = now('Asia/Taipei')->toDateString();
        $run = $service->run($day, null, $operator);
        self::assertSame(3, $run->anomalies);
        foreach (['amount_or_quantity', 'remote_only', 'aged_pending'] as $kind) {
            $this->assertDatabaseHas('reconciliation_findings', ['kind' => $kind, 'status' => 'open']);
        }
        $service->run($day, null, $operator);
        $this->assertDatabaseCount('reconciliation_findings', 3);
        $finding = ReconciliationFinding::where('order_reference', $orphan->order_reference)->firstOrFail();
        $service->resolve($operator, $finding->id, '已確認為合成帳冊孤兒資料');
        $this->assertDatabaseHas('audit_logs', ['action' => 'reconciliation.reviewed', 'user_id' => $operator->id]);

        $statement->update(['quantity' => 1]);
        $service->run($day, $product->supplier_id, $operator);
        $this->assertDatabaseHas('reconciliation_findings', ['kind' => 'amount_or_quantity', 'status' => 'resolved']);
        $this->assertDatabaseHas('reconciliation_findings', ['kind' => 'remote_only', 'status' => 'reviewed']);
        $this->assertDatabaseCount('tickets', 1);
        $this->actingAs(User::factory()->create())->get('/ops/reconciliation')->assertForbidden();
    }
}
