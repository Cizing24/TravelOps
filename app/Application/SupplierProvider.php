<?php

namespace App\Application;

use App\Models\OrderItem;

interface SupplierProvider
{
    /** @return array{result: string, error: ?string} */
    public function issue(OrderItem $item, string $traceId, int $attempt): array;
}
