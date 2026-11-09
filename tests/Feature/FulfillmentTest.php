<?php

namespace Tests\Feature;

use App\Application\Checkout;
use App\Application\Issuance;
use App\Application\RefundOrder;
use App\Infrastructure\MockGateway;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\CreatesOrders;
use Tests\TestCase;

final class FulfillmentTest extends TestCase
{
    use CreatesOrders;
    use DatabaseMigrations;

    public function test_journey_a_customer_purchases_and_views_the_original_snapshot(): void
    {
        $password = 'test-password-only-24';
        $customer = User::factory()->create(['password' => $password]);
        $product = Product::factory()->stocked(5, 4)->create();
        $venues = $product->venues()->pluck('venues.name')->all();
        $this->get('/')->assertOk()->assertSee($product->name);
        $this->get('/products/'.$product->slug)->assertOk()->assertSee($venues[0]);
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $customer->email, 'password' => $password])->assertRedirect('/my/orders');
        $key = (string) Str::uuid();
        $request = ['product_id' => $product->id, 'quantity' => 2, 'idempotency_key' => $key];
        $response = $this->postJson('/orders', $request)->assertCreated();
        $reference = $response->json('reference');
        $this->postJson('/orders', $request)->assertOk()->assertJsonPath('reference', $reference);
        $this->postJson('/orders', array_replace($request, ['quantity' => 1]))->assertConflict();
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => 3]);

        $product->update(['price' => 1, 'valid_days' => 1, 'listed' => false]);
        $product->venues()->detach();
        $this->get('/products/'.$product->slug)->assertNotFound();
        $this->get('/my/orders')->assertOk()->assertSee($reference);
        $this->post('/my/orders/'.$reference.'/simulate', ['scenario' => 'success'])->assertRedirect();
        $order = Order::where('reference', $reference)->firstOrFail();
        $this->assertSame(64000, $order->total);
        $this->assertSame('paid', $order->status);
        $this->assertSame(0, $order->tickets()->count());
        $this->issue($order);
        $this->get('/my/orders/'.$reference)->assertOk()->assertSee('已開券');
        $ticket = $order->tickets()->firstOrFail();
        $this->assertSame(14, $ticket->valid_days);
        $this->get('/my/tickets')->assertOk()->assertSee('0 / 4');
        $this->get('/my/tickets/'.$ticket->id)->assertOk()->assertSee('data:image/svg+xml;base64,', false)->assertSee($venues[0]);
        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseCount('ticket_entitlements', 8);
    }

    public function test_journey_b_bundle_has_independent_venue_rights_and_realtime_expiry(): void
    {
        $product = Product::factory()->stocked(4, 4)->create();
        $order = $this->place($product);
        $this->pay($order);
        $this->issue($order);
        $ticket = $order->tickets()->firstOrFail();
        $venues = $product->venues()->orderBy('venues.id')->get();
        $a = User::factory()->create(['role' => 'merchant']);
        $b = User::factory()->create(['role' => 'merchant']);
        $a->venues()->attach([$venues[0]->id, $venues[2]->id]);
        $b->venues()->attach($venues[1]->id);
        $this->travelTo(CarbonImmutable::parse('2026-11-09T16:00:00Z'));
        $payload = ['venue_id' => $venues[0]->id, 'code' => $ticket->code];
        $this->actingAs($a)->get('/merchant/redemptions')->assertOk()->assertSee($venues[0]->name)->assertDontSee($venues[1]->name);
        $this->postJson('/merchant/redemptions', $payload)->assertCreated();
        $this->assertSame('2026-11-09T16:00:00+00:00', $ticket->refresh()->first_used_at->toIso8601String());
        $this->assertSame('2026-11-23T16:00:00+00:00', $ticket->expires_at->toIso8601String());
        $this->assertSame(3, $ticket->entitlements()->where('status', 'unused')->count());
        $this->postJson('/merchant/redemptions', $payload)->assertConflict()->assertJsonPath('code', 'already_redeemed');
        $payload['venue_id'] = $venues[1]->id;
        $this->postJson('/merchant/redemptions', $payload)->assertForbidden()->assertJsonPath('code', 'venue_forbidden');
        $this->actingAs($b)->postJson('/merchant/redemptions', $payload)->assertCreated();
        $this->travelTo($ticket->expires_at);
        $payload['venue_id'] = $venues[2]->id;
        $this->actingAs($a)->postJson('/merchant/redemptions', $payload)->assertConflict()->assertJsonPath('code', 'expired');
        $this->assertDatabaseCount('redemptions', 2);
        $this->assertSame(2, $ticket->entitlements()->where('status', 'unused')->count());
    }

    public function test_journey_c_signed_webhooks_are_deduplicated_and_cannot_regress_payment(): void
    {
        $order = $this->place();
        $gateway = app(MockGateway::class);
        $event = $gateway->event($order, 'succeeded');
        $this->call('POST', '/webhooks/mock', [], [], [], ['HTTP_ACCEPT' => 'application/json'], $event['body'])->assertUnauthorized();
        $this->assertDatabaseCount('webhook_events', 0);
        $send = fn (array $payload) => $this->call('POST', '/webhooks/mock', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'HTTP_X_TRAVELOPS_SIGNATURE' => $payload['signature'],
        ], $payload['body']);
        config(['travelops.demo' => false]);
        $send($event)->assertNotFound();
        config(['travelops.demo' => true]);
        $send($gateway->event($order, 'failed'))->assertOk()->assertJsonPath('outcome', 'failed');
        $send($event)->assertOk()->assertJsonPath('outcome', 'paid');
        $send($event)->assertOk()->assertJsonPath('outcome', 'paid');
        $send($event)->assertOk()->assertJsonPath('outcome', 'paid');
        config(['travelops.demo' => false]);
        $send($event)->assertNotFound();
        config(['travelops.demo' => true]);
        $send($gateway->event($order, 'failed'))->assertOk()->assertJsonPath('outcome', 'ignored_final');
        $conflict = str_replace('succeeded', 'failed', $event['body']);
        $send(['body' => $conflict, 'signature' => $gateway->signature($conflict)])->assertConflict()->assertJsonPath('code', 'event_conflict');
        $this->issue($order);
        $this->issue($order);
        $this->assertDatabaseCount('webhook_events', 3);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'succeeded']);
        $this->assertDatabaseCount('outbox_events', 1);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_issuance_exhaustion_is_visible_and_operator_can_compensate(): void
    {
        $product = Product::factory()->stocked()->create();
        $product->supplier->update(['scenario' => 'retry_exhausted']);
        $order = $this->place($product);
        $this->pay($order);
        for ($i = 0; $i < 3; $i++) {
            $this->issue($order);
            $this->travel(45)->seconds();
        }
        $this->issue($order);
        $event = $order->issuance()->firstOrFail();
        $this->assertSame('permanently_failed', $event->status);
        $this->assertSame(3, $event->attempts);
        $this->assertDatabaseCount('tickets', 0);
        $operator = User::factory()->create(['role' => 'operator']);
        $this->actingAs($operator)->get('/ops/outbox-events')->assertOk()->assertSee('追蹤編號');
        $product->supplier->update(['scenario' => 'success']);
        app(Issuance::class)->requeue($operator, $event);
        $this->issue($order);
        $this->assertDatabaseHas('outbox_events', ['id' => $event->id, 'status' => 'succeeded', 'attempts' => 4]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $operator->id, 'action' => 'issuance.requeued']);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_unpaid_order_cancellation_and_expiry_release_stock_once(): void
    {
        $product = Product::factory()->stocked(2)->create();
        $customer = User::factory()->create();
        $cancelled = $this->place($product, $customer);
        $expired = $this->place($product, $customer);
        $this->actingAs($customer)->post('/my/orders/'.$cancelled->reference.'/cancel')->assertRedirect();
        $this->post('/my/orders/'.$cancelled->reference.'/cancel')->assertRedirect();
        $this->travel(16)->minutes();
        $this->assertTrue(app(Checkout::class)->expire($expired->id));
        $this->assertFalse(app(Checkout::class)->expire($expired->id));
        $this->pay($expired);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => 2]);
        $this->assertSame('expired', $expired->refresh()->status);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_journey_e_refund_and_role_boundaries(): void
    {
        $product = Product::factory()->stocked(4, 4)->create();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $operator = User::factory()->create(['role' => 'operator']);
        $admin = User::factory()->create(['role' => 'admin']);
        $merchant = User::factory()->create(['role' => 'merchant']);
        $merchant->venues()->attach($venue = $product->venues()->firstOrFail()->id);
        $unused = $this->place($product, $customer);
        $partial = $this->place($product, $customer);
        foreach ([$unused, $partial] as $order) {
            $this->pay($order);
            $this->issue($order);
        }
        $ticket = $unused->tickets()->firstOrFail();

        $this->actingAs($other)->get('/my/orders/'.$unused->reference)->assertForbidden();
        $this->get('/my/tickets/'.$ticket->id)->assertForbidden();
        $this->post('/my/orders/'.$unused->reference.'/refund')->assertForbidden();
        $this->get('/ops')->assertForbidden();
        $this->postJson('/merchant/redemptions', ['venue_id' => $venue, 'code' => $ticket->code])->assertForbidden();
        $this->actingAs($operator)->get('/ops')->assertOk();
        $this->get('/ops/orders')->assertOk()->assertSee('交易紀錄');
        $this->get('/ops/users')->assertForbidden();
        $this->get('/ops/products')->assertForbidden();
        $this->flushSession();
        $this->actingAs($admin)->get('/ops/users')->assertOk();
        $this->get('/ops/products')->assertOk();

        $this->flushSession();
        $this->actingAs($customer)->post('/my/orders/'.$unused->reference.'/refund')->assertRedirect();
        $this->post('/my/orders/'.$unused->reference.'/refund')->assertRedirect();
        $refund = $unused->refund()->firstOrFail();
        $service = app(RefundOrder::class);
        try {
            $service->review($customer, $refund, true);
            self::fail('Customer must not be able to review refunds');
        } catch (AuthorizationException) {
            self::assertSame('requested', $refund->refresh()->status);
        }
        $this->assertSame('approved', $service->review($operator, $refund, true)->status);
        $service->process($refund->id);
        $this->assertSame('processed', $refund->refresh()->status);
        $this->assertSame('processed', $service->review($operator, $refund, true)->status);
        $this->assertSame('revoked', $ticket->refresh()->status);
        $this->assertSame('returned', $unused->reservation()->firstOrFail()->status);
        $this->assertDatabaseHas('payments', ['order_id' => $unused->id, 'status' => 'refunded']);
        $this->assertSame(1, AuditLog::where('action', 'refund.processed')->count());
        $this->actingAs($merchant)->postJson('/merchant/redemptions', ['venue_id' => $venue, 'code' => $ticket->code])
            ->assertConflict()->assertJsonPath('code', 'revoked');
        $this->postJson('/merchant/redemptions', ['venue_id' => $venue, 'code' => $partial->tickets()->firstOrFail()->code])->assertCreated();
        $partialRefund = $service->request($customer, $partial);
        $this->assertSame('rejected', $service->review($operator, $partialRefund, true)->status);
        $this->assertSame('fulfilled', $partial->refresh()->status);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => 3]);
    }
}
