<?php

namespace Tests\Feature;

use App\Application\Checkout;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_sales_share_online_stock_and_replays_do_not_capture_twice(): void
    {
        $product = Product::factory()->stocked(3)->create();
        $store = Store::create(['name' => '測試門市 A']);
        $clerk = User::factory()->create(['role' => 'pos_clerk']);
        $clerk->stores()->attach($store);
        $customer = User::factory()->create();
        app(Checkout::class)->place($customer, $product->id, 1, (string) Str::uuid());

        $sale = [
            'product_id' => $product->id, 'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(), 'method' => 'cash',
        ];
        $url = '/pos/'.$store->id.'/sales';
        $this->actingAs($clerk)->postJson($url, $sale)->assertCreated()->assertJsonPath('status', 'paid');
        $this->postJson($url, $sale)->assertOk();
        $this->postJson($url, array_replace($sale, ['method' => 'mock_card']))->assertConflict();
        $this->postJson($url, array_replace($sale, ['method' => 'mock_card', 'idempotency_key' => (string) Str::uuid()]))
            ->assertCreated();
        $this->postJson($url, array_replace($sale, ['idempotency_key' => (string) Str::uuid()]))
            ->assertConflict()->assertJsonPath('code', 'sold_out');

        $this->assertDatabaseCount('orders', 3);
        $this->assertDatabaseCount('outbox_events', 2);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => 0]);
        $this->assertDatabaseHas('orders', ['source_channel' => 'pos', 'store_id' => $store->id, 'payment_method' => 'cash']);
    }

    public function test_pos_store_membership_scopes_sales_and_supervisor_closing(): void
    {
        $store = Store::create(['name' => '測試門市 A']);
        $other = Store::create(['name' => '測試門市 B']);
        $clerk = User::factory()->create(['role' => 'pos_clerk']);
        $clerk->stores()->attach($store);
        $supervisor = User::factory()->create(['role' => 'pos_supervisor']);
        $supervisor->stores()->attach($store);

        $this->actingAs($clerk)->get('/pos/'.$store->id)->assertOk();
        $this->get('/pos/'.$other->id)->assertForbidden();
        $this->postJson('/pos/'.$other->id.'/sales')->assertForbidden();
        $this->get('/pos/'.$store->id.'/closing')->assertForbidden();
        $this->actingAs($supervisor)->get('/pos/'.$store->id.'/closing')->assertOk();
        $this->get('/pos/'.$other->id.'/closing')->assertForbidden();
    }
}
