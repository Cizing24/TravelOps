<?php

namespace App\Application;

use App\Domain\OrderState;
use App\Domain\RuleViolation;
use App\Jobs\IssueTickets;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class Issuance
{
    private const READY = ['pending', 'retryable_failed'];

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
        DB::transaction(function () use ($orderId, $eventId): void {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $event = OutboxEvent::query()->lockForUpdate()->findOrFail($eventId);
            if (! in_array($event->status, self::READY, true) || $event->available_at->isFuture()
                || ! OrderState::canIssue($order->status)) {
                return;
            }
            /** @var OrderItem $item */
            $item = $order->item()->with('supplier')->firstOrFail();
            $attempt = $event->attempts + 1;
            $cycle = $event->cycle_attempts + 1;
            ['result' => $result, 'error' => $error] = $this->supplier->issue($item, $event->trace_id, $cycle);
            $event->history()->create(['number' => $attempt, 'result' => $result, 'error' => $error]);
            $status = match (true) {
                $result === 'success' => 'succeeded',
                $result === 'permanent', $cycle >= 3 => 'permanently_failed',
                default => 'retryable_failed',
            };
            $event->update([
                'status' => $status, 'attempts' => $attempt, 'cycle_attempts' => $cycle, 'last_error' => $error,
                'available_at' => now()->addSeconds($status === 'retryable_failed' ? 15 * $cycle : 0),
                'queued_until' => null,
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
            Audit::record('issuance.'.$status, null, $order->id, ['attempt' => $attempt], $event->trace_id);
        }, 5);
    }

    public function requeue(User $actor, OutboxEvent $event): void
    {
        Gate::forUser($actor)->authorize('operate');
        DB::transaction(function () use ($actor, $event): void {
            $order = Order::query()->lockForUpdate()->findOrFail($event->order_id);
            $locked = OutboxEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($order->status !== 'fulfilling' || $locked->status !== 'permanently_failed') {
                throw new RuleViolation('not_retryable', '只有停止重試的開券事件可以重新排程。');
            }
            $locked->update(['status' => 'pending', 'cycle_attempts' => 0, 'available_at' => now(), 'queued_until' => null]);
            Audit::record('issuance.requeued', $actor->id, $order->id, [], $locked->trace_id);
        }, 5);
    }
}
