<?php

namespace App\Application;

use App\Domain\OrderState;
use App\Domain\RuleViolation;
use App\Infrastructure\MockRefundGateway;
use App\Jobs\ProcessRefund;
use App\Models\Order;
use App\Models\Refund;
use App\Models\TicketEntitlement;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class RefundOrder
{
    public const BLOCK_REDEMPTION = ['approved', 'submitted', 'pending', 'requires_review'];

    private const READY = ['approved', 'submitted', 'pending'];

    public function __construct(private Checkout $checkout, private RefundGateway $gateway) {}

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

    public function review(User $actor, Refund $refund, bool $approve, string $scenario = 'success'): Refund
    {
        Gate::forUser($actor)->authorize('operate');
        if (! array_key_exists($scenario, MockRefundGateway::SCENARIOS)) {
            throw new RuleViolation('invalid_scenario', '無效的退款情境。', 422);
        }

        return DB::transaction(function () use ($actor, $refund, $approve, $scenario): Refund {
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
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            if (! OrderState::captured($order->status) || $payment->status !== 'succeeded'
                || $locked->amount !== $payment->amount || $locked->amount !== $order->total) {
                throw new RuleViolation('refund_unavailable', '訂單目前無法退款。');
            }
            $locked->update(['reviewed_by' => $actor->id, 'status' => 'approved', 'scenario' => $scenario, 'available_at' => now()]);
            Audit::record('refund.approved', $actor->id, $order->id, ['before' => 'requested', 'after' => 'approved'], $locked->idempotency_key);

            return $locked;
        }, 5);
    }

    public function relay(): void
    {
        $ids = Refund::whereIn('status', self::READY)->where('available_at', '<=', now())->orderBy('available_at')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            ProcessRefund::dispatch($id)->onConnection('redis');
        }
    }

    public function process(int $id): void
    {
        $refund = Refund::findOrFail($id);
        $claimed = DB::transaction(function () use ($refund): ?Refund {
            Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if (! in_array($locked->status, self::READY, true) || $locked->available_at?->isFuture()) {
                return null;
            }
            if ($locked->cycle_attempts >= 4) {
                $this->record($locked, 'requires_review', '退款重試達上限。', null);

                return null;
            }
            $locked->update([
                'status' => 'submitted', 'available_at' => now()->addMinutes(2),
                'attempts' => $locked->attempts + 1, 'cycle_attempts' => $locked->cycle_attempts + 1,
            ]);
            Audit::record('refund.submitted', null, $locked->order_id, ['attempt' => $locked->attempts], $locked->idempotency_key);

            return $locked;
        }, 5);
        if ($claimed === null) {
            return;
        }
        $outcome = $this->gateway->queryRefund($claimed) ?? $this->gateway->submitRefund($claimed);
        DB::transaction(function () use ($claimed, $outcome): void {
            $order = Order::query()->lockForUpdate()->findOrFail($claimed->order_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($claimed->id);
            if ($locked->status === 'submitted' && $locked->attempts === $claimed->attempts) {
                $this->apply($order, $locked, $outcome);
            }
        }, 5);
    }

    public function confirm(Refund $refund, string $eventId, string $outcome, string $hash): string
    {
        return DB::transaction(function () use ($refund, $eventId, $outcome, $hash): string {
            $order = Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            $key = ['provider' => 'refund', 'external_event_id' => $eventId];
            $created = WebhookEvent::query()->insertOrIgnore($key + [
                'order_id' => $order->id, 'body_hash' => $hash, 'outcome' => 'received',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $event = WebhookEvent::where($key)->lockForUpdate()->firstOrFail();
            if (! $created) {
                if (! hash_equals($event->body_hash, $hash)) {
                    throw new RuleViolation('event_conflict', '回呼識別碼已用於其他內容。');
                }

                return $event->outcome;
            }
            $result = 'ignored_final';
            if (in_array($locked->status, ['submitted', 'pending', 'requires_review'], true)) {
                if ($outcome !== 'pending') {
                    $this->apply($order, $locked, $outcome);
                }
                $result = $locked->status;
            }
            $event->update(['outcome' => $result]);

            return $result;
        }, 5);
    }

    public function requeue(User $actor, Refund $refund, string $reason): void
    {
        Gate::forUser($actor)->authorize('operate');
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new RuleViolation('invalid_reason', '請輸入重新排程原因。', 422);
        }
        DB::transaction(function () use ($actor, $refund, $reason): void {
            Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($locked->status !== 'requires_review') {
                throw new RuleViolation('not_retryable', '只有需要人工處理的退款可以重新排程。');
            }
            $locked->update(['status' => 'pending', 'available_at' => now(), 'cycle_attempts' => 0]);
            Audit::record('refund.requeued', $actor->id, $locked->order_id, [
                'reason' => $reason, 'before' => 'requires_review', 'after' => 'pending',
            ], $locked->idempotency_key);
        }, 5);
    }

    private function apply(Order $order, Refund $refund, string $outcome): void
    {
        if ($outcome === 'failed') {
            $this->record($refund, 'failed', '模擬退款遭拒。', null);

            return;
        }
        if ($outcome !== 'succeeded') {
            $reason = match ($outcome) {
                'temporary' => '退款服務暫時無法處理。',
                'unknown' => '退款結果未知，將查詢既有請求。',
                default => null,
            };
            $this->record($refund, 'pending', $reason, 10 * $refund->cycle_attempts + random_int(0, 3));

            return;
        }

        if ($order->payment()->where('status', 'succeeded')->where('amount', $refund->amount)->update(['status' => 'refunded']) !== 1) {
            $this->record($refund, 'requires_review', '實付金額或狀態與退款不符。', null);

            return;
        }
        $this->checkout->releaseInventory($order, 'sold', 'returned', $refund->reviewed_by);
        $order->issuance()->whereIn('status', ['pending', 'retryable_failed', 'permanently_failed'])
            ->update(['status' => 'cancelled', 'queued_until' => null]);
        $order->issuance()->where('status', 'succeeded')->update(['cancel_available_at' => now()]);
        TicketEntitlement::whereIn('ticket_id', $order->tickets()->select('tickets.id'))->update(['status' => 'revoked']);
        $order->tickets()->update(['status' => 'revoked']);
        $order->update(['status' => 'refunded']);
        $refund->update(['processed_at' => now()]);
        $this->record($refund, 'processed', null, null);
        app(NotificationCenter::class)->order($order, 'refunded');
    }

    private function record(Refund $refund, string $status, ?string $reason, ?int $delay): void
    {
        $before = $refund->status;
        $refund->update(['status' => $status, 'reason' => $reason, 'available_at' => $delay === null ? null : now()->addSeconds($delay)]);
        Audit::record('refund.'.$status, $refund->reviewed_by, $refund->order_id, [
            'before' => $before, 'after' => $status, 'attempt' => $refund->attempts, 'reason' => $reason,
        ], $refund->idempotency_key);
    }
}
