<?php

namespace App\Infrastructure;

use App\Application\SupplierProvider;
use App\Models\OrderItem;

final class MockSupplier implements SupplierProvider
{
    public const SCENARIOS = [
        'success' => '正常開券',
        'timeout_once' => '首次逾時',
        'temporary_once' => '首次暫時失敗',
        'retry_exhausted' => '持續暫時失敗',
        'permanent' => '永久失敗',
    ];

    /** @return array{result: string, error: ?string} */
    public function issue(OrderItem $item, string $traceId, int $attempt): array
    {
        $scenario = $item->supplier->scenario;

        return match (true) {
            $scenario === 'permanent' => ['result' => 'permanent', 'error' => '供應商拒絕開券（模擬）'],
            $scenario === 'timeout_once' && $attempt === 1 => ['result' => 'temporary', 'error' => '供應商回應逾時（模擬）'],
            $scenario === 'retry_exhausted', $scenario === 'temporary_once' && $attempt === 1 => ['result' => 'temporary', 'error' => '供應商暫時無法開券（模擬）'],
            default => ['result' => 'success', 'error' => null],
        };
    }
}
