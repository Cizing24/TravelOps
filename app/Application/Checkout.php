<?php

namespace App\Application;

use App\Domain\Money;
use App\Domain\OrderState;
use App\Domain\RuleViolation;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class Checkout
{
    public function place(User $customer, int $productId, int $quantity, string $key, ?Store $store = null, string $method = 'mock_online'): Order
    {
        if ($quantity < 1 || $quantity > 10 || ! Str::isUuid($key)
            || ! in_array($method, $store === null ? ['mock_online'] : ['cash', 'mock_card'], true)) {
            throw new RuleViolation('invalid_checkout', '數量須為 1 到 10，且需提供有效的下單識別碼。', 422);
        }

        return DB::transaction(function () use ($customer, $productId, $quantity, $key, $store, $method): Order {
            $actor = User::query()->lockForUpdate()->findOrFail($customer->id);
            if ($store === null) {
                Gate::forUser($actor)->authorize('create', Order::class);
            } else {
                abort_unless($actor->staffsStore($store), 403);
                Store::query()->lockForUpdate()->findOrFail($store->id);
            }
            $existing = Order::where('user_id', $actor->id)->where('idempotency_key', $key)->first();
            if ($existing === null && $store !== null) {
                $existing = Order::where('store_id', $store->id)->where('idempotency_key', $key)->first();
            }
            if ($existing !== null) {
                $item = $existing->item()->firstOrFail();
                if ($item->product_id !== $productId || $item->quantity !== $quantity
                    || $existing->store_id !== $store?->id || $existing->payment_method !== $method) {
                    throw new RuleViolation('key_conflict', '同一識別碼不能用於不同訂單內容。');
                }

                return $existing;
            }

            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $venues = $product->venues()->orderBy('venues.id')->sharedLock()->get()
                ->map(fn ($venue): array => ['id' => $venue->id, 'name' => $venue->name])->all();
            if (! $product->listed || ($product->kind === 'single' ? count($venues) !== 1 : count($venues) < 2)) {
                throw new RuleViolation('unavailable', '這項商品目前無法購買。');
            }
            $inventory = InventoryBalance::where('product_id', $productId)->lockForUpdate()->firstOrFail();
            if ($inventory->available < $quantity) {
                throw new RuleViolation('sold_out', '剩餘票量不足。');
            }

            $total = Money::total($product->price, $quantity);
            $order = Order::create([
                'user_id' => $customer->id,
                'store_id' => $store?->id,
                'source_channel' => $store === null ? 'online' : 'pos',
                'payment_method' => $method,
                'reference' => (string) Str::uuid(),
                'idempotency_key' => $key,
                'status' => 'pending_payment',
                'total' => $total,
                'currency' => 'TWD',
                'pay_by' => now()->addMinutes(15),
            ]);
            $order->item()->create([
                'product_id' => $productId, 'supplier_id' => $product->supplier_id,
                'name' => $product->name, 'unit_price' => $product->price, 'quantity' => $quantity,
                'valid_days' => $product->valid_days, 'venues' => $venues,
            ]);
            $inventory->decrement('available', $quantity);
            $order->reservation()->create(['product_id' => $productId, 'quantity' => $quantity, 'status' => 'held']);
            $this->recordMovement($order, $productId, 'reservation', -$quantity, '建立庫存預留', $actor->id);
            $order->payment()->create(['idempotency_key' => (string) Str::uuid(), 'status' => 'initiated', 'amount' => $total]);
            Audit::record('order.placed', $customer->id, $order->id, ['store_id' => $store?->id, 'payment_method' => $method], $order->reference);

            return $order;
        }, 5);
    }

    public function cancel(User $customer, Order $order): void
    {
        Gate::forUser($customer)->authorize('view', $order);
        DB::transaction(function () use ($order, $customer): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($locked->status, ['cancelled', 'expired'], true)) {
                return;
            }
            if (! OrderState::awaitingPayment($locked->status)) {
                throw new RuleViolation('already_paid', '已付款訂單請改為申請退款。');
            }
            $this->releasePending($locked, now()->gte($locked->pay_by) ? 'expired' : 'cancelled', $customer->id);
        }, 5);
    }

    public function expire(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId): bool {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            if (! OrderState::awaitingPayment($order->status) || now()->lt($order->pay_by)) {
                return false;
            }
            $this->releasePending($order, 'expired', null);

            return true;
        }, 5);
    }

    public function releasePending(Order $order, string $status, ?int $actor): void
    {
        $order->update(['status' => $status]);
        $this->releaseInventory($order, 'held', 'released', $actor);
        $order->payment()->where('status', 'initiated')->update(['status' => 'failed']);
        Audit::record('order.'.$status, $actor, $order->id);
    }

    public function releaseInventory(Order $order, string $expected, string $result, ?int $actor = null): void
    {
        /** @var InventoryReservation $reservation */
        $reservation = $order->reservation()->lockForUpdate()->firstOrFail();
        if ($reservation->status !== $expected) {
            throw new RuleViolation('inventory_conflict', '庫存狀態不符，請聯繫營運人員。');
        }
        InventoryBalance::where('product_id', $reservation->product_id)->lockForUpdate()
            ->firstOrFail()->increment('available', $reservation->quantity);
        $reservation->update(['status' => $result]);
        $this->recordMovement(
            $order, $reservation->product_id, $result === 'returned' ? 'refund' : 'release', $reservation->quantity,
            $result === 'returned' ? '退款回補庫存' : '釋放未付款庫存', $actor,
        );
    }

    public function consume(Order $order): void
    {
        if ($order->reservation()->where('status', 'held')->update(['status' => 'sold']) !== 1) {
            throw new RuleViolation('inventory_conflict', '庫存預留已失效。');
        }
        $reservation = $order->reservation()->firstOrFail();
        $this->recordMovement($order, $reservation->product_id, 'sale', 0, '確認付款，將預留轉為已售', $order->user_id);
    }

    private function recordMovement(Order $order, int $productId, string $kind, int $delta, string $reason, ?int $actor): void
    {
        InventoryMovement::create([
            'order_id' => $order->id, 'product_id' => $productId, 'kind' => $kind,
            'delta' => $delta, 'reason' => $reason, 'user_id' => $actor,
        ]);
    }
}
