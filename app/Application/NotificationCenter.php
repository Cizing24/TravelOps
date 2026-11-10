<?php

namespace App\Application;

use App\Domain\RuleViolation;
use App\Infrastructure\MockGateway;
use App\Jobs\DeliverNotification;
use App\Models\NotificationDelivery;
use App\Models\NotificationMessage;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class NotificationCenter
{
    public const TITLES = [
        'paid' => '付款已確認', 'issued' => '票券已開立', 'refunded' => '退款已確認',
        'expiry_3' => '票券將在三天後到期', 'expiry_1' => '票券將在一天後到期',
        'supplier_failed' => '供應商開券需要人工處理', 'reconciliation' => '對帳發現差異',
    ];

    public function __construct(private MockGateway $gateway, private NotificationChannel $channel) {}

    public function order(Order $order, string $template): void
    {
        $user = $order->user;
        if ($order->source_channel === 'online' && $user->role === 'customer') {
            $this->publish($user, $template, $order->reference, $order->id, null);
        }
    }

    public function operations(string $template, string $event, ?int $orderId): void
    {
        User::whereIn('role', ['operator', 'admin'])->chunkById(100, function ($users) use ($template, $event, $orderId): void {
            foreach ($users as $user) {
                $this->publish($user, $template, $event, $orderId, null);
            }
        });
    }

    public function remind(): void
    {
        foreach ([3, 1] as $days) {
            $start = now('Asia/Taipei')->addDays($days)->startOfDay();
            Ticket::where('status', 'active')->where('expires_at', '>=', $start->copy()->utc())
                ->where('expires_at', '<', $start->addDay()->utc())
                ->whereHas('order', fn ($query) => $query->where('source_channel', 'online'))
                ->with('order.user')->chunkById(100, function ($tickets) use ($days): void {
                    foreach ($tickets as $ticket) {
                        $order = $ticket->order;
                        if ($order !== null && $order->user->role === 'customer' && $order->user->remind_expiry) {
                            $this->publish($order->user, 'expiry_'.$days, (string) $ticket->id, $order->id, $ticket->id);
                        }
                    }
                });
        }
    }

    public function relay(): void
    {
        if (! $this->gateway->enabled()) {
            return;
        }
        $ids = NotificationDelivery::whereIn('status', ['queued', 'failed'])
            ->where('available_at', '<=', now())->orderBy('available_at')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            DeliverNotification::dispatch($id)->onConnection('redis');
        }
    }

    public function deliver(int $id): void
    {
        if (! $this->gateway->enabled()) {
            return;
        }
        $delivery = DB::transaction(function () use ($id): ?NotificationDelivery {
            $locked = NotificationDelivery::query()->lockForUpdate()->findOrFail($id);
            if (! in_array($locked->status, ['queued', 'failed'], true) || $locked->available_at === null || $locked->available_at->isFuture()) {
                return null;
            }
            $user = $locked->message->user;
            if (! $this->allowed($user, $locked->channel, $locked->message->template)) {
                $locked->update(['status' => 'suppressed', 'available_at' => null]);

                return null;
            }
            $locked->update(['available_at' => now()->addMinutes(2), 'attempts' => $locked->attempts + 1, 'cycle_attempts' => $locked->cycle_attempts + 1]);

            return $locked;
        }, 5);
        if ($delivery === null) {
            return;
        }
        try {
            $sent = $this->channel->send($delivery);
        } catch (Throwable $error) {
            report($error);
            $sent = false;
        }
        NotificationDelivery::whereKey($id)->where('attempts', $delivery->attempts)->update([
            'status' => $sent ? 'sent' : 'failed',
            'last_error' => $sent ? null : 'mock_channel_unavailable',
            'available_at' => $sent || $delivery->cycle_attempts >= 3 ? null : now()->addSeconds(15 * $delivery->cycle_attempts + random_int(0, 3)),
        ]);
    }

    public function requeue(User $actor, NotificationDelivery $delivery, string $reason): void
    {
        Gate::forUser($actor)->authorize('operate');
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new RuleViolation('invalid_reason', '請輸入重新排程原因。', 422);
        }
        DB::transaction(function () use ($actor, $delivery, $reason): void {
            $locked = NotificationDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if ($locked->status !== 'failed' || $locked->available_at !== null) {
                throw new RuleViolation('not_retryable', '只有重試結束的失敗通知可以重新排程。');
            }
            $locked->update(['status' => 'queued', 'available_at' => now(), 'cycle_attempts' => 0]);
            Audit::record('notification.requeued', $actor->id, $locked->message->order_id, [
                'delivery_id' => $locked->id, 'reason' => $reason, 'before' => 'failed', 'after' => 'queued',
            ]);
        }, 5);
    }

    private function publish(User $user, string $template, string $event, ?int $orderId, ?int $ticketId): void
    {
        DB::transaction(function () use ($user, $template, $event, $orderId, $ticketId): void {
            $message = NotificationMessage::query()->createOrFirst([
                'user_id' => $user->id, 'template' => $template, 'event_key' => $event,
            ], ['order_id' => $orderId, 'ticket_id' => $ticketId, 'title' => self::TITLES[$template]]);
            if (! $message->wasRecentlyCreated) {
                return;
            }
            foreach (['email', 'line'] as $channel) {
                $enabled = $this->gateway->enabled() && $this->allowed($user, $channel, $template);
                NotificationDelivery::create([
                    'notification_message_id' => $message->id, 'channel' => $channel,
                    'status' => $enabled ? 'queued' : 'suppressed', 'available_at' => $enabled ? now() : null,
                ]);
            }
        }, 5);
    }

    private function allowed(User $user, string $channel, string $template): bool
    {
        $enabled = $channel === 'email' ? $user->notification_email : $user->notification_line;

        return $enabled && (! str_starts_with($template, 'expiry_') || $user->remind_expiry);
    }
}
