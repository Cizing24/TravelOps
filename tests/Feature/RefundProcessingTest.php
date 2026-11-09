<?php

namespace Tests\Feature;

use App\Application\Issuance;
use App\Application\RefundOrder;
use App\Infrastructure\MockRefundGateway;
use App\Models\Product;
use App\Models\SupplierStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesOrders;
use Tests\TestCase;

final class RefundProcessingTest extends TestCase
{
    use CreatesOrders;
    use RefreshDatabase;

    public function test_pending_refund_blocks_redemption_and_callback_restores_inventory_once(): void
    {
        $product = Product::factory()->stocked(1)->create();
        $order = $this->place($product);
        $this->pay($order);
        $this->issue($order);
        $merchant = User::factory()->create(['role' => 'merchant']);
        $venue = $product->venues()->firstOrFail();
        $merchant->venues()->attach($venue);
        $ticket = $order->tickets()->firstOrFail();
        $operator = User::factory()->create(['role' => 'operator']);
        $refunds = app(RefundOrder::class);
        $refund = $refunds->request($operator, $order);
        $refunds->review($operator, $refund, true, 'delayed');
        $refunds->process($refund->id);
        self::assertSame('pending', $refund->refresh()->status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'succeeded']);
        $this->actingAs($merchant)->postJson('/merchant/redemptions', ['venue_id' => $venue->id, 'code' => $ticket->code])
            ->assertConflict()->assertJsonPath('code', 'refund_pending');

        $this->travel(16)->seconds();
        $send = function (string $status, int $amount, bool $valid = true) use ($order, $refund) {
            $body = json_encode([
                'event_id' => $status === 'succeeded' ? $refund->idempotency_key : (string) Str::uuid(),
                'request' => $refund->idempotency_key, 'order' => $order->reference,
                'amount' => $amount, 'currency' => 'TWD', 'status' => $status, 'sent_at' => now()->timestamp,
            ], JSON_THROW_ON_ERROR);
            $signature = $valid ? app(MockRefundGateway::class)->signature($body) : 'fake';

            return $this->call('POST', '/webhooks/mock-refunds', [], [], [], [
                'HTTP_ACCEPT' => 'application/json', 'HTTP_X_TRAVELOPS_SIGNATURE' => $signature,
            ], $body);
        };
        $send('succeeded', $refund->amount, false)->assertUnauthorized();
        $send('succeeded', $refund->amount + 1)->assertUnprocessable();
        $this->freezeTime(function () use ($send, $refund): void {
            for ($delivery = 0; $delivery < 3; $delivery++) {
                $send('succeeded', $refund->amount)->assertOk()->assertJsonPath('outcome', 'processed');
            }
        });
        $send('pending', $refund->amount)->assertOk()->assertJsonPath('outcome', 'ignored_final');
        $refunds->process($refund->id);
        self::assertSame('processed', $refund->refresh()->status);
        self::assertSame('revoked', $ticket->refresh()->status);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => 1]);
        self::assertSame(1, $order->reservation()->where('status', 'returned')->count());
        $issuance = $order->issuance()->firstOrFail();
        self::assertNotNull($issuance->cancel_available_at);
        $this->travelTo($issuance->cancel_available_at->addSecond());
        app(Issuance::class)->cancel($issuance->id);
        app(Issuance::class)->cancel($issuance->id);
        self::assertNotNull($issuance->refresh()->cancelled_at);
        self::assertSame(1, $issuance->cancel_attempts);
        self::assertNotNull(SupplierStatement::where('request_key', $issuance->trace_id)->sole()->cancelled_at);
    }

    public function test_decline_preserves_capture_and_unknown_outcome_is_queried(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $refunds = app(RefundOrder::class);
        foreach (['declined' => 'failed', 'timeout' => 'processed'] as $scenario => $status) {
            $order = $this->place();
            $this->pay($order);
            $this->issue($order);
            $refund = $refunds->request($operator, $order);
            $refunds->review($operator, $refund, true, $scenario);
            $refunds->process($refund->id);
            if ($scenario === 'timeout') {
                self::assertSame('pending', $refund->refresh()->status);
                $this->travel(14)->seconds();
                $refunds->process($refund->id);
            } else {
                self::assertSame('fulfilled', $order->refresh()->status);
                self::assertSame('succeeded', $order->payment()->firstOrFail()->status);
            }
            self::assertSame($status, $refund->refresh()->status);
            self::assertSame($refund->id, $refunds->request($operator, $order)->id);
        }
        $this->assertDatabaseCount('refund_statements', 2);
    }
}
