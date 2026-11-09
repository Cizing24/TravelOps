<?php

namespace App\Application;

use App\Models\NotificationDelivery;

interface NotificationChannel
{
    public function send(NotificationDelivery $delivery): bool;
}
