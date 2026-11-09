<?php

namespace App\Jobs;

use App\Application\NotificationCenter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(NotificationCenter $notifications): void
    {
        $notifications->deliver($this->deliveryId);
    }
}
