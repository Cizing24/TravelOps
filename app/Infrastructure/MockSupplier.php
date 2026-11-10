<?php

namespace App\Infrastructure;

use App\Application\SupplierProvider;
use App\Jobs\DeliverMockSupplierCallback;
use App\Models\OrderItem;
use App\Models\SupplierStatement;
use Throwable;

final class MockSupplier implements SupplierProvider
{
    public const SCENARIOS = [
        'success' => '正常開券',
        'timeout_once' => '首次逾時',
        'temporary_once' => '首次暫時失敗',
        'retry_exhausted' => '持續暫時失敗',
        'permanent' => '永久失敗',
        'lost_reply' => '開券成功但回應遺失',
    ];

    public const ADAPTERS = ['synchronous' => '同步確認', 'webhook' => '非同步回呼'];

    public function __construct(private MockGateway $gateway) {}

    /** @return array{result: string, error: ?string} */
    public function issue(OrderItem $item, string $traceId, int $attempt): array
    {
        if (! $this->gateway->enabled()) {
            return ['result' => 'permanent', 'error' => '模擬供應商未啟用。'];
        }

        $scenario = $item->supplier->scenario;
        $failure = match (true) {
            $scenario === 'permanent' => ['result' => 'permanent', 'error' => '供應商拒絕開券（模擬）'],
            $scenario === 'timeout_once' && $attempt === 1 => ['result' => 'temporary', 'error' => '供應商回應逾時（模擬）'],
            $scenario === 'retry_exhausted', $scenario === 'temporary_once' && $attempt === 1 => ['result' => 'temporary', 'error' => '供應商暫時無法開券（模擬）'],
            default => null,
        };
        if ($failure !== null) {
            return $failure;
        }

        $async = $item->supplier->adapter === 'webhook';
        $statement = SupplierStatement::query()->createOrFirst(['request_key' => $traceId], [
            'supplier_id' => $item->supplier_id, 'order_reference' => $item->order->reference,
            'quantity' => $item->quantity, 'webhook' => $async, 'ready_at' => now()->addSeconds($async ? 5 : 0),
        ]);
        if ($async && $statement->wasRecentlyCreated) {
            try {
                DeliverMockSupplierCallback::dispatch($statement->id)->onConnection('redis')->delay($statement->ready_at);
            } catch (Throwable $error) {
                report($error);
            }
        }
        if ($scenario === 'lost_reply' && $statement->wasRecentlyCreated) {
            return ['result' => 'unknown', 'error' => '供應商回應遺失，將查詢既有開券結果。'];
        }

        return ['result' => $statement->ready_at->isFuture() ? 'pending' : 'success', 'error' => null];
    }

    /** @return array{result: string, error: ?string}|null */
    public function queryIssuance(OrderItem $item, string $traceId): ?array
    {
        if (! $this->gateway->enabled()) {
            return null;
        }
        $statement = SupplierStatement::where('supplier_id', $item->supplier_id)->where('request_key', $traceId)->first();

        return $statement === null ? null : [
            'result' => $statement->ready_at->isFuture() ? 'pending' : 'success', 'error' => null,
        ];
    }

    public function signature(int $supplierId, string $body): string
    {
        return $this->gateway->signature("supplier:{$supplierId}\n".$body);
    }

    public function cancelIssuance(OrderItem $item, string $traceId): bool
    {
        if (! $this->gateway->enabled()) {
            return false;
        }
        $statement = SupplierStatement::where('request_key', $traceId)->first();
        if ($statement === null) {
            return true;
        }
        if ($statement->supplier_id !== $item->supplier_id || $statement->order_reference !== $item->order->reference) {
            return false;
        }
        SupplierStatement::whereKey($statement->id)->whereNull('cancelled_at')->update(['cancelled_at' => now()]);

        return true;
    }
}
