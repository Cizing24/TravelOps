<?php

namespace App\Application;

use App\Domain\RuleViolation;
use App\Infrastructure\MockGateway;
use App\Infrastructure\MockRefundGateway;
use App\Models\Refund;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

final class RefundCallback
{
    public function __construct(
        private MockGateway $gateway,
        private MockRefundGateway $refundGateway,
        private RefundOrder $refunds,
    ) {}

    public function receive(string $body, string $signature): string
    {
        abort_unless($this->gateway->enabled(), 404);
        try {
            return $this->validateAndApply($body, $signature);
        } catch (RuleViolation|ValidationException $error) {
            Audit::record('refund.callback_rejected', null, null, [
                'error' => $error instanceof RuleViolation ? $error->reason : 'invalid_payload',
            ]);
            throw $error;
        }
    }

    private function validateAndApply(string $body, string $signature): string
    {
        if (strlen($body) > 4096 || ! hash_equals($this->refundGateway->signature($body), $signature)) {
            throw new RuleViolation('invalid_signature', '無效的退款回呼簽章。', 401);
        }
        try {
            $data = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuleViolation('invalid_payload', '退款回呼格式有誤。', 422);
        }
        $data = Validator::make(is_array($data) ? $data : [], [
            'event_id' => 'required|uuid', 'request' => 'required|uuid', 'order' => 'required|uuid',
            'amount' => 'required|integer|min:1', 'currency' => 'required|in:TWD',
            'status' => 'required|in:pending,succeeded,failed', 'sent_at' => 'required|integer|between:0,4102444800',
        ])->validate();
        if (abs(now()->timestamp - (int) $data['sent_at']) > 300) {
            throw new RuleViolation('stale_event', '退款回呼已超出時間限制。', 422);
        }
        $refund = Refund::where('idempotency_key', $data['request'])->with('order')->first();
        if ($refund === null || $refund->amount !== (int) $data['amount'] || $refund->order->reference !== $data['order']
            || $refund->order->currency !== $data['currency']
            || ($data['status'] !== 'pending' && $this->refundGateway->queryRefund($refund) !== $data['status'])) {
            throw new RuleViolation('refund_mismatch', '退款回呼與實際退款請求不符。', 422);
        }

        return $this->refunds->confirm($refund, $data['event_id'], $data['status'], hash('sha256', $body));
    }
}
