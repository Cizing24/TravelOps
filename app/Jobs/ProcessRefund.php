<?php

namespace App\Jobs;

use App\Application\RefundOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessRefund implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public int $timeout = 30;

    public function __construct(public int $refundId) {}

    public function uniqueId(): string
    {
        return (string) $this->refundId;
    }

    public function handle(RefundOrder $refunds): void
    {
        $refunds->process($this->refundId);
    }
}
