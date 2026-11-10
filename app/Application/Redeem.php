<?php

namespace App\Application;

use App\Domain\RuleViolation;
use App\Domain\TicketWindow;
use App\Models\Order;
use App\Models\Redemption;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class Redeem
{
    public function ticket(User $actor, int $venueId, string $code): Redemption
    {
        Gate::forUser($actor)->authorize('redeem');
        if (! preg_match('/\A[a-f0-9]{48}\z/', $code)) {
            throw new RuleViolation('invalid_code', '核銷代碼格式不正確。', 422);
        }

        return DB::transaction(function () use ($actor, $venueId, $code): Redemption {
            $staff = User::query()->lockForUpdate()->findOrFail($actor->id);
            if ($staff->role !== 'merchant' || ! $staff->venues()->whereKey($venueId)->exists()) {
                throw new RuleViolation('venue_forbidden', '你沒有此場館的核銷權限。', 403);
            }
            $ticket = Ticket::where('code_hash', hash('sha256', $code))->first();
            if ($ticket === null) {
                throw new RuleViolation('invalid_code', '找不到有效的核銷代碼。', 404);
            }
            $order = Order::query()->lockForUpdate()->findOrFail($ticket->order_id);
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $entitlement = $ticket->entitlements()->where('venue_id', $venueId)->lockForUpdate()->first();
            if ($entitlement === null) {
                throw new RuleViolation('wrong_venue', '這張票券不適用於此場館。');
            }
            if ($ticket->status === 'revoked' || $order->status === 'refunded') {
                throw new RuleViolation('revoked', '票券已撤銷。');
            }
            if ($order->refund()->whereIn('status', RefundOrder::BLOCK_REDEMPTION)->lockForUpdate()->first() !== null) {
                throw new RuleViolation('refund_pending', '票券退款處理中，暫停核銷。');
            }
            if ($entitlement->status === 'redeemed') {
                throw new RuleViolation('already_redeemed', '此場館權益已核銷。');
            }
            $now = now()->toImmutable();
            if ($ticket->status === 'expired' || TicketWindow::expired($ticket->expires_at, $now)) {
                throw new RuleViolation('expired', '票券有效期已屆滿。');
            }
            if ($ticket->status !== 'active' || $order->status !== 'fulfilled' || $entitlement->status !== 'unused') {
                throw new RuleViolation('not_usable', '這張票券目前無法核銷。');
            }
            if ($ticket->first_used_at === null) {
                $ticket->update(['first_used_at' => $now, 'expires_at' => TicketWindow::expiresAt($now, $ticket->valid_days)]);
            }
            $entitlement->update(['status' => 'redeemed']);
            $trace = (string) Str::uuid();
            $redemption = $entitlement->redemption()->create([
                'venue_id' => $venueId, 'user_id' => $staff->id, 'redeemed_at' => $now, 'trace_id' => $trace,
            ]);
            Audit::record('ticket.redeemed', $staff->id, $order->id, ['venue_id' => $venueId, 'ticket_id' => $ticket->id], $trace);

            return $redemption;
        }, 5);
    }
}
