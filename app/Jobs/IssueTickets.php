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

    public bool $cancel = false;

    public function __construct(public int $eventId, bool $cancel = false)
    {
        $this->cancel = $cancel;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 20];
    }

    public function handle(Issuance $issuance): void
    {
        if ($this->cancel) {
            $issuance->cancel($this->eventId);
        } else {
            $issuance->process($this->eventId);
        }
    }
}
