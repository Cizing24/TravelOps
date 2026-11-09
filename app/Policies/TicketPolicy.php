<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

final class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        $order = $ticket->order;
        $store = $order?->store;

        return $store === null
            ? $user->role === 'customer' && $order?->source_channel === 'online' && $user->id === $order->user_id
            : $user->staffsStore($store);
    }
}
