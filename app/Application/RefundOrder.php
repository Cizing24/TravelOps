<?php

namespace App\Application;

use App\Domain\OrderState;
use App\Domain\RuleViolation;
use App\Models\Order;
use App\Models\Refund;
use App\Models\TicketEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class RefundOrder
{
    public function __construct(private Checkout $checkout) {}

    public function request(User $actor, Order $order): Refund
    {
        $store = $order->store;
        if (! $actor->operates() && ! ($store !== null && $actor->role === 'pos_supervisor' && $actor->staffsStore($store))) {
            Gate::forUser($actor)->authorize('view', $order);
        }

        return DB::transaction(function () use ($actor, $order): Refund {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $existing = $locked->refund()->first();
            if ($existing !== null) {
                return $existing;
            }
            if (! OrderState::captured($locked->status)) {
                throw new RuleViolation('refund_unavailable', '目前的訂單狀態無法申請退款。');
            }
            $refund = $locked->refund()->create([
                'requested_by' => $actor->id, 'status' => 'requested', 'amount' => $locked->total,
                'idempotency_key' => (string) Str::uuid(),
            ]);
            Audit::record('refund.requested', $actor->id, $locked->id, [], $refund->idempotency_key);

            return $refund;
        }, 5);
    }

    public function review(User $actor, Refund $refund, bool $approve): Refund
    {
        Gate::forUser($actor)->authorize('operate');

        return DB::transaction(function () use ($actor, $refund, $approve): Refund {
            $order = Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($locked->status !== 'requested') {
                return $locked;
            }

            $used = TicketEntitlement::whereIn('ticket_id', $order->tickets()->select('tickets.id'))
                ->where('status', 'redeemed')->exists();
            $reason = OrderState::refundRejection($used, $approve);
            if ($reason !== null) {
                $locked->update(['reviewed_by' => $actor->id, 'status' => 'rejected', 'reason' => $reason]);
                Audit::record('refund.rejected', $actor->id, $order->id, ['reason' => $reason], $locked->idempotency_key);

                return $locked;
            }

            $issuance = $order->issuance()->first();
            $lastResult = $issuance?->history()->orderByDesc('number')->first()?->result;
            if ($issuance?->lease_token !== null || in_array($lastResult, ['pending', 'unknown'], true)) {
                throw new RuleViolation('issuance_in_flight', '供應商開券結果尚未確認，請稍後重試退款。');
            }
            if (! OrderState::captured($order->status)
                || $order->payment()->where('status', 'succeeded')->update(['status' => 'refunded']) !== 1) {
                throw new RuleViolation('refund_unavailable', '訂單目前無法退款。');
            }
            $this->checkout->releaseInventory($order, 'sold', 'returned', $actor->id);
            $order->issuance()->whereIn('status', ['pending', 'retryable_failed', 'permanently_failed'])
                ->update(['status' => 'cancelled', 'queued_until' => null]);
            TicketEntitlement::whereIn('ticket_id', $order->tickets()->select('tickets.id'))->update(['status' => 'revoked']);
            $order->tickets()->update(['status' => 'revoked']);
            $order->update(['status' => 'refunded']);
            $locked->update(['reviewed_by' => $actor->id, 'status' => 'processed']);
            Audit::record('refund.processed', $actor->id, $order->id, [], $locked->idempotency_key);

            return $locked;
        }, 5);
    }
}
