<?php

namespace App\Application;

use App\Domain\RuleViolation;
use App\Infrastructure\MockGateway;
use App\Infrastructure\MockSupplier;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\SupplierStatement;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

final class SupplierCallback
{
    public function __construct(
        private MockGateway $gateway,
        private MockSupplier $supplier,
        private Issuance $issuance,
    ) {}

    public function receive(int $supplierId, string $body, string $signature): string
    {
        abort_unless($this->gateway->enabled(), 404);
        try {
            return $this->validateAndApply($supplierId, $body, $signature);
        } catch (RuleViolation|ValidationException $error) {
            Audit::record('supplier.callback_rejected', null, null, [
                'supplier_id' => $supplierId,
                'error' => $error instanceof RuleViolation ? $error->reason : 'invalid_payload',
            ]);
            throw $error;
        }
    }

    private function validateAndApply(int $supplierId, string $body, string $signature): string
    {
        if (strlen($body) > 4096 || ! hash_equals($this->supplier->signature($supplierId, $body), $signature)) {
            throw new RuleViolation('invalid_signature', '無效的供應商回呼簽章。', 401);
        }
        try {
            $data = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuleViolation('invalid_payload', '供應商回呼格式有誤。', 422);
        }
        $data = Validator::make(is_array($data) ? $data : [], [
            'event_id' => 'required|uuid',
            'request' => 'required|uuid',
            'status' => 'required|in:pending,succeeded',
            'sent_at' => 'required|integer|between:0,4102444800',
        ])->validate();
        if (abs(now()->timestamp - (int) $data['sent_at']) > 300) {
            throw new RuleViolation('stale_event', '供應商回呼已超出時間限制。', 422);
        }

        $event = OutboxEvent::where('trace_id', $data['request'])->first();
        $item = $event === null ? null : OrderItem::where('order_id', $event->order_id)->first();
        $statement = SupplierStatement::where('request_key', $data['request'])
            ->where('supplier_id', $supplierId)->where('webhook', true)->first();
        if ($event === null || $item === null || $item->supplier_id !== $supplierId || $statement === null
            || $statement->order_reference !== $item->order->reference || $statement->quantity !== $item->quantity
            || ($data['status'] === 'succeeded' && $statement->ready_at->isFuture())) {
            throw new RuleViolation('supplier_mismatch', '供應商或開券結果與請求不符。', 422);
        }

        return $this->issuance->confirmCallback($event, $item, $data['event_id'], $data['status'], hash('sha256', $body));
    }
}
