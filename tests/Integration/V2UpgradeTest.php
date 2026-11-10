<?php

namespace Tests\Integration;

use App\Application\RefundOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class V2UpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_additive_migrations_preserve_mvp_orders_tickets_and_processed_refunds(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 7])->assertSuccessful();
        self::assertFalse(Schema::hasColumn('orders', 'source_channel'));
        $product = Product::factory()->stocked(1)->create();
        $venue = $product->venues()->firstOrFail();
        $customer = User::factory()->create();
        $operator = User::factory()->create(['role' => 'operator']);
        $id = DB::table('orders')->insertGetId([
            'user_id' => $customer->id, 'reference' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(),
            'total' => 32000, 'status' => 'refunded', 'pay_by' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = Order::findOrFail($id);
        $code = bin2hex(random_bytes(24));
        $ticket = $order->tickets()->create([
            'sequence' => 1, 'status' => 'revoked', 'valid_days' => 14, 'code' => $code, 'code_hash' => hash('sha256', $code),
        ]);
        $ticket->entitlements()->create(['venue_id' => $venue->id, 'venue_name' => $venue->name, 'status' => 'revoked']);
        $refund = $order->refund()->create([
            'requested_by' => $customer->id, 'reviewed_by' => $operator->id,
            'idempotency_key' => (string) Str::uuid(), 'amount' => 32000, 'status' => 'processed',
        ]);

        $this->artisan('migrate')->assertSuccessful();
        self::assertSame('online', $order->refresh()->source_channel);
        self::assertSame('refunded', $order->status);
        self::assertSame($code, $ticket->refresh()->code);
        $this->assertDatabaseHas('ticket_entitlements', ['ticket_id' => $ticket->id, 'status' => 'revoked']);
        app(RefundOrder::class)->process($refund->id);
        self::assertSame('processed', $refund->refresh()->status);
        $this->assertDatabaseCount('refund_statements', 0);
    }
}
