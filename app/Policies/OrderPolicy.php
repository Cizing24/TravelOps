<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->role === 'customer' && $order->source_channel === 'online' && $user->id === $order->user_id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'customer';
    }
}
