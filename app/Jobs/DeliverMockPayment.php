<?php

namespace App\Jobs;

use App\Application\PaymentWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverMockPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $body, public string $signature) {}

    public function handle(PaymentWebhook $webhook): void
    {
        $webhook->receive($this->body, $this->signature);
    }
}
