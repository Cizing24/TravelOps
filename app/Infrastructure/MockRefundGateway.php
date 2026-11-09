<?php

namespace App\Infrastructure;

use App\Application\RefundGateway;
use App\Jobs\DeliverMockRefundCallback;
use App\Models\Refund;
use App\Models\RefundStatement;
use Throwable;

final class MockRefundGateway implements RefundGateway
{
    public const SCENARIOS = [
        'success' => '立即確認', 'delayed' => '延遲確認', 'declined' => '拒絕',
        'temporary_once' => '首次暫時失敗', 'timeout' => '回應逾時',
    ];

    public function __construct(private MockGateway $gateway) {}

    public function submitRefund(Refund $refund): string
    {
        if (! $this->gateway->enabled()) {
            return 'unknown';
        }
        if ($refund->scenario === 'temporary_once' && $refund->cycle_attempts === 1) {
            return 'temporary';
        }
        $statement = RefundStatement::query()->createOrFirst(['request_key' => $refund->idempotency_key], [
            'order_reference' => $refund->order->reference, 'amount' => $refund->amount,
            'result' => $refund->scenario === 'declined' ? 'failed' : 'succeeded',
            'ready_at' => now()->addSeconds($refund->scenario === 'delayed' ? 15 : 0),
        ]);
        if ($refund->scenario === 'delayed' && $statement->wasRecentlyCreated) {
            try {
                DeliverMockRefundCallback::dispatch($statement->id)->onConnection('redis')->delay($statement->ready_at);
            } catch (Throwable $error) {
                report($error);
            }
        }
        if ($refund->scenario === 'timeout' && $statement->wasRecentlyCreated) {
            return 'unknown';
        }

        return $statement->ready_at->isFuture() ? 'pending' : $statement->result;
    }

    public function queryRefund(Refund $refund): ?string
    {
        if (! $this->gateway->enabled()) {
            return null;
        }
        $statement = RefundStatement::where('request_key', $refund->idempotency_key)->first();
        if ($statement === null) {
            return null;
        }
        if ($statement->order_reference !== $refund->order->reference || $statement->amount !== $refund->amount) {
            return 'unknown';
        }

        return $statement->ready_at->isFuture() ? 'pending' : $statement->result;
    }

    public function signature(string $body): string
    {
        return $this->gateway->signature("refund\n".$body);
    }
}
