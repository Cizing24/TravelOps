<?php

namespace App\Jobs;

use App\Application\SupplierCallback;
use App\Infrastructure\MockGateway;
use App\Infrastructure\MockSupplier;
use App\Models\SupplierStatement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverMockSupplierCallback implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $statementId) {}

    public function handle(MockGateway $gateway, MockSupplier $supplier, SupplierCallback $callback): void
    {
        if (! $gateway->enabled()) {
            return;
        }
        $statement = SupplierStatement::findOrFail($this->statementId);
        $body = json_encode([
            'event_id' => $statement->request_key, 'request' => $statement->request_key,
            'status' => 'succeeded', 'sent_at' => $statement->ready_at->timestamp,
        ], JSON_THROW_ON_ERROR);
        $callback->receive($statement->supplier_id, $body, $supplier->signature($statement->supplier_id, $body));
    }
}
