<?php

namespace App\Jobs;

use App\Application\Issuance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class IssueTickets implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $eventId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 20];
    }

    public function handle(Issuance $issuance): void
    {
        $issuance->process($this->eventId);
    }
}
