<?php

namespace App\Infrastructure;

use App\Application\NotificationChannel;
use App\Models\NotificationDelivery;

final class MockNotificationChannel implements NotificationChannel
{
    public function send(NotificationDelivery $delivery): bool
    {
        return $delivery->channel !== 'line' || $delivery->message->template !== 'expiry_1' || $delivery->cycle_attempts > 1;
    }
}
