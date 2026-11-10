<?php

namespace App\Jobs;

use App\Application\RefundCallback;
use App\Infrastructure\MockGateway;
use App\Infrastructure\MockRefundGateway;
use App\Models\RefundStatement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverMockRefundCallback implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $statementId) {}

    public function handle(MockGateway $gateway, MockRefundGateway $refundGateway, RefundCallback $callback): void
    {
        if (! $gateway->enabled()) {
            return;
        }
        $statement = RefundStatement::findOrFail($this->statementId);
        $body = json_encode([
            'event_id' => $statement->request_key, 'request' => $statement->request_key,
            'order' => $statement->order_reference, 'amount' => $statement->amount, 'currency' => 'TWD',
            'status' => $statement->result, 'sent_at' => $statement->ready_at->timestamp,
        ], JSON_THROW_ON_ERROR);
        $callback->receive($body, $refundGateway->signature($body));
    }
}
