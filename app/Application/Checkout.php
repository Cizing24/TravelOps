<?php

namespace App\Application;

use App\Domain\Money;
use App\Domain\OrderState;
use App\Domain\RuleViolation;
use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class Checkout
{
    public function place(User $customer, int $productId, int $quantity, string $key): Order
    {
        Gate::forUser($customer)->authorize('create', Order::class);
        if ($quantity < 1 || $quantity > 10 || ! Str::isUuid($key)) {
            throw new RuleViolation('invalid_checkout', '數量須為 1 到 10，且需提供有效的下單識別碼。', 422);
        }

        return DB::transaction(function () use ($customer, $productId, $quantity, $key): Order {
            User::query()->lockForUpdate()->findOrFail($customer->id);
            $existing = Order::where('user_id', $customer->id)->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                $item = $existing->item()->firstOrFail();
                if ($item->product_id !== $productId || $item->quantity !== $quantity) {
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
            $order->payment()->create(['idempotency_key' => (string) Str::uuid(), 'status' => 'initiated', 'amount' => $total]);
            Audit::record('order.placed', $customer->id, $order->id);

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
        $this->releaseInventory($order, 'held', 'released');
        $order->payment()->where('status', 'initiated')->update(['status' => 'failed']);
        Audit::record('order.'.$status, $actor, $order->id);
    }

    public function releaseInventory(Order $order, string $expected, string $result): void
    {
        /** @var InventoryReservation $reservation */
        $reservation = $order->reservation()->lockForUpdate()->firstOrFail();
        if ($reservation->status !== $expected) {
            throw new RuleViolation('inventory_conflict', '庫存狀態不符，請聯繫營運人員。');
        }
        InventoryBalance::where('product_id', $reservation->product_id)->lockForUpdate()
            ->firstOrFail()->increment('available', $reservation->quantity);
        $reservation->update(['status' => $result]);
    }

    public function consume(Order $order): void
    {
        if ($order->reservation()->where('status', 'held')->update(['status' => 'sold']) !== 1) {
            throw new RuleViolation('inventory_conflict', '庫存預留已失效。');
        }
    }
}
