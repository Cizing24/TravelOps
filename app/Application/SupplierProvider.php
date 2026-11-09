<?php

namespace App\Application;

use App\Models\OrderItem;

interface SupplierProvider
{
    /**
     * The trace ID is the provider idempotency key, including after a lost reply.
     *
     * @return array{result: string, error: ?string}
     */
    public function issue(OrderItem $item, string $traceId, int $attempt): array;

    /** @return array{result: string, error: ?string}|null */
    public function queryIssuance(OrderItem $item, string $traceId): ?array;
}
