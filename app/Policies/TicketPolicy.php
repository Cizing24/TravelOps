<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

final class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->role === 'customer' && $user->id === $ticket->order?->user_id;
    }
}
