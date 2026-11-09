<?php

namespace App\Application;

use App\Domain\OrderState;
use App\Domain\RuleViolation;
use App\Jobs\IssueTickets;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

final class Issuance
{
    private const READY = ['pending', 'retryable_failed', 'awaiting_callback'];

    public function __construct(private SupplierProvider $supplier) {}

    public function relay(): int
    {
        $ids = OutboxEvent::whereIn('status', self::READY)->where('available_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('queued_until')->orWhere('queued_until', '<=', now()))
            ->orderBy('available_at')->orderBy('id')->limit(100)->pluck('id');
        $queued = 0;
        foreach ($ids as $id) {
            try {
                $queued += (int) DB::transaction(function () use ($id): bool {
                    $event = OutboxEvent::query()->lockForUpdate()->findOrFail($id);
                    if (! in_array($event->status, self::READY, true)
                        || $event->available_at->isFuture() || $event->queued_until?->isFuture()) {
                        return false;
                    }
                    Bus::dispatch((new IssueTickets($event->id))->onConnection('redis')->beforeCommit());
                    $event->update(['queued_until' => now()->addMinutes(2)]);

                    return true;
                });
            } catch (Throwable $error) {
                report($error);
                break;
            }
        }

        return $queued;
    }

    public function process(int $eventId): void
    {
        $orderId = OutboxEvent::findOrFail($eventId)->order_id;
        $lease = (string) Str::uuid();
        $request = DB::transaction(function () use ($orderId, $eventId, $lease): ?OutboxEvent {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $event = OutboxEvent::query()->lockForUpdate()->findOrFail($eventId);
            if (! in_array($event->status, self::READY, true) || $event->available_at->isFuture()
                || ! OrderState::canIssue($order->status)) {
                return null;
            }
            $event->update(['lease_token' => $lease, 'available_at' => now()->addMinutes(2), 'queued_until' => null]);

            return $event;
        }, 5);
        if ($request === null) {
            return;
        }

        /** @var OrderItem $item */
        $item = OrderItem::where('order_id', $orderId)->with('supplier')->firstOrFail();
        $cycle = $request->cycle_attempts + 1;
        ['result' => $result, 'error' => $error] = $this->supplier->queryIssuance($item, $request->trace_id)
            ?? $this->supplier->issue($item, $request->trace_id, $cycle);

        DB::transaction(function () use ($orderId, $eventId, $lease, $item, $result, $error): void {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $event = OutboxEvent::query()->lockForUpdate()->findOrFail($eventId);
            if ($event->lease_token !== $lease || ! OrderState::canIssue($order->status)) {
                return;
            }
            $this->recordResult($order, $event, $item, $result, $error);
        }, 5);
    }

    public function confirmCallback(OutboxEvent $event, OrderItem $item, string $eventId, string $status, string $hash): string
    {
        return DB::transaction(function () use ($event, $item, $eventId, $status, $hash): string {
            $order = Order::query()->lockForUpdate()->findOrFail($event->order_id);
            $locked = OutboxEvent::query()->lockForUpdate()->findOrFail($event->id);
            $key = ['provider' => 'supplier', 'external_event_id' => $item->supplier_id.':'.$eventId];
            $claimed = WebhookEvent::query()->insertOrIgnore($key + [
                'order_id' => $order->id, 'body_hash' => $hash, 'outcome' => 'received',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $inbox = WebhookEvent::where($key)->lockForUpdate()->firstOrFail();
            if (! $claimed) {
                if (! hash_equals($inbox->body_hash, $hash)) {
                    throw new RuleViolation('event_conflict', '回呼識別碼已用於其他內容。');
                }

                return $inbox->outcome;
            }

            $outcome = 'ignored_final';
            if (OrderState::canIssue($order->status)) {
                $outcome = 'awaiting_callback';
                if ($status === 'succeeded') {
                    $this->recordResult($order, $locked, $item, 'success', null);
                    $outcome = 'succeeded';
                }
            }
            $inbox->update(['outcome' => $outcome]);

            return $outcome;
        }, 5);
    }

    private function recordResult(Order $order, OutboxEvent $event, OrderItem $item, string $result, ?string $error): void
    {
        $attempt = $event->attempts + 1;
        $cycle = $event->cycle_attempts + 1;
        $event->history()->create(['number' => $attempt, 'result' => $result, 'error' => $error]);
        $status = match (true) {
            $result === 'success' => 'succeeded',
            $result === 'permanent', $cycle >= 3 => 'permanently_failed',
            $result === 'pending' => 'awaiting_callback',
            default => 'retryable_failed',
        };
        $delay = match ($status) {
            'retryable_failed' => 15 * $cycle + random_int(0, 3),
            'awaiting_callback' => 15 * $cycle,
            default => 0,
        };
        $event->update([
            'status' => $status, 'attempts' => $attempt, 'cycle_attempts' => $cycle, 'last_error' => $error,
            'available_at' => now()->addSeconds($delay), 'queued_until' => null, 'lease_token' => null,
        ]);

        if ($result === 'success') {
            for ($sequence = 1; $sequence <= $item->quantity; $sequence++) {
                $code = bin2hex(random_bytes(24));
                $ticket = $order->tickets()->create([
                    'sequence' => $sequence, 'status' => 'active', 'valid_days' => $item->valid_days,
                    'code' => $code, 'code_hash' => hash('sha256', $code),
                ]);
                foreach ($item->venues as $venue) {
                    $ticket->entitlements()->create(['venue_id' => $venue['id'], 'venue_name' => $venue['name'], 'status' => 'unused']);
                }
            }
        }
        $order->update(['status' => $result === 'success' ? 'fulfilled' : 'fulfilling']);
        Audit::record('issuance.'.$status, null, $order->id, ['attempt' => $attempt, 'supplier_id' => $item->supplier_id], $event->trace_id);
    }

    public function requeue(User $actor, OutboxEvent $event, string $reason = '例行開券補償'): void
    {
        Gate::forUser($actor)->authorize('operate');
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new RuleViolation('invalid_reason', '請輸入 1 至 500 字的重新排程原因。', 422);
        }
        DB::transaction(function () use ($actor, $event, $reason): void {
            $order = Order::query()->lockForUpdate()->findOrFail($event->order_id);
            $locked = OutboxEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($order->status !== 'fulfilling' || $locked->status !== 'permanently_failed') {
                throw new RuleViolation('not_retryable', '只有停止重試的開券事件可以重新排程。');
            }
            $locked->update(['status' => 'pending', 'cycle_attempts' => 0, 'available_at' => now(), 'queued_until' => null]);
            Audit::record('issuance.requeued', $actor->id, $order->id, [
                'reason' => $reason, 'before' => 'permanently_failed', 'after' => 'pending',
            ], $locked->trace_id);
        }, 5);
    }
}
