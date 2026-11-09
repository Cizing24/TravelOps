<?php

namespace Tests\Feature;

use App\Application\RefundOrder;
use App\Filament\Pages\ListOutboxEvent;
use App\Filament\Pages\ListProduct;
use App\Filament\Pages\ListRefund;
use App\Filament\Pages\ListSupplier;
use App\Filament\Pages\ListUser;
use App\Filament\Pages\ListVenue;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\CreatesOrders;
use Tests\TestCase;

final class OperationsTest extends TestCase
{
    use CreatesOrders;
    use DatabaseMigrations;

    public function test_admin_can_create_catalog_assign_venues_and_adjust_inventory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Livewire::test(ListSupplier::class)
            ->callAction('create', data: ['name' => '示意供應商', 'scenario' => 'success'])
            ->assertHasNoActionErrors();
        $supplier = Supplier::where('name', '示意供應商')->firstOrFail();
        Livewire::test(ListVenue::class)
            ->callAction('create', data: ['name' => '測試新增場館', 'supplier_id' => $supplier->id])
            ->assertHasNoActionErrors();
        $venue = Venue::where('name', '測試新增場館')->firstOrFail();
        Livewire::test(ListProduct::class)->callAction('create', data: [
            'name' => '單館測試票', 'slug' => 'ops-single', 'supplier_id' => $supplier->id,
            'kind' => 'single', 'description' => '場館權益測試', 'price' => 32125,
            'valid_days' => 14, 'venue_ids' => [$venue->id], 'listed' => true, 'available' => 5,
        ])->assertHasNoActionErrors();
        $product = Product::where('slug', 'ops-single')->firstOrFail();
        self::assertSame([$venue->id], $product->venues()->allRelatedIds()->all());
        self::assertSame(5, $product->inventory()->firstOrFail()->available);
        Livewire::test(ListProduct::class)
            ->callAction(TestAction::make('stock')->table($product), data: ['delta' => -2])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'available' => 3]);
        Livewire::test(ListUser::class)->callAction('create', data: [
            'name' => '新場館人員', 'email' => 'new-merchant@travelops.test', 'password' => 'sample-fixture-password',
            'role' => 'merchant', 'venue_ids' => [$venue->id],
        ])->assertHasNoActionErrors();
        $newUser = User::where('email', 'new-merchant@travelops.test')->firstOrFail();
        $this->assertDatabaseHas('merchant_users', ['user_id' => $newUser->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'access.user_saved']);
    }

    public function test_operator_can_retry_failed_issuance_and_approve_refund_through_filament(): void
    {
        $product = Product::factory()->stocked()->create();
        $product->supplier->update(['scenario' => 'permanent']);
        $order = $this->place($product);
        $this->pay($order);
        $this->issue($order);
        $event = $order->issuance()->firstOrFail();
        self::assertSame('permanently_failed', $event->status);
        $this->assertDatabaseCount('tickets', 0);

        $operator = User::factory()->create(['role' => 'operator']);
        $this->actingAs($operator);
        $product->supplier->update(['scenario' => 'success']);
        Livewire::test(ListOutboxEvent::class)
            ->callAction(TestAction::make('retry')->table($event))
            ->assertHasNoActionErrors();
        self::assertSame('pending', $event->refresh()->status);
        $this->issue($order);
        $refund = app(RefundOrder::class)->request($operator, $order);
        Livewire::test(ListRefund::class)
            ->callAction(TestAction::make('approve')->table($refund))
            ->assertHasNoActionErrors();
        self::assertSame('processed', $refund->refresh()->status);
        $this->assertDatabaseHas('tickets', ['order_id' => $order->id, 'status' => 'revoked']);
    }
}
