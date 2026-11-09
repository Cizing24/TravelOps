<?php

namespace App\Application;

use App\Domain\OrderState;
use App\Domain\RuleViolation;
use App\Infrastructure\MockGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JsonException;

final class PaymentWebhook
{
    public function __construct(private MockGateway $gateway, private Checkout $checkout) {}

    public function receive(string $body, string $signature): string
    {
        abort_unless($this->gateway->enabled(), 404);
        if (strlen($body) > 8192 || ! hash_equals($this->gateway->signature($body), $signature)) {
            throw new RuleViolation('invalid_signature', '無效的回呼簽章。', 401);
        }

        try {
            $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuleViolation('invalid_payload', '回呼資料格式有誤。', 422);
        }
        $data = Validator::make(is_array($data) ? $data : [], [
            'event_id' => ['required', 'uuid'],
            'order' => ['required', 'uuid'],
            'payment' => ['required', 'uuid'],
            'status' => ['required', 'in:succeeded,failed'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'in:TWD'],
        ])->validate();

        return DB::transaction(function () use ($data, $body): string {
            $order = Order::where('reference', $data['order'])->lockForUpdate()->firstOrFail();
            /** @var Payment $payment */
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            if ($payment->idempotency_key !== $data['payment'] || $payment->amount !== $data['amount'] || $order->currency !== $data['currency']) {
                throw new RuleViolation('payment_mismatch', '付款參考編號、金額或幣別不符。', 422);
            }

            $hash = hash('sha256', $body);
            $key = ['provider' => 'mock', 'external_event_id' => $data['event_id']];
            $claimed = WebhookEvent::query()->insertOrIgnore($key + [
                'order_id' => $order->id, 'body_hash' => $hash, 'outcome' => 'received',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $event = WebhookEvent::where($key)->lockForUpdate()->firstOrFail();
            if (! $claimed) {
                if ($event->body_hash !== $hash) {
                    throw new RuleViolation('event_conflict', '回呼識別碼已用於其他內容。');
                }

                return $event->outcome;
            }

            $outcome = 'ignored_final';
            if (OrderState::awaitingPayment($order->status)) {
                if (now()->gte($order->pay_by)) {
                    $this->checkout->releasePending($order, 'expired', null);
                    $outcome = 'ignored_expired';
                } elseif ($data['status'] === 'failed') {
                    $payment->update(['status' => 'failed']);
                    $outcome = 'failed';
                } else {
                    $this->checkout->consume($order);
                    $order->update(['status' => 'paid']);
                    $payment->update(['status' => 'succeeded', 'paid_at' => now()]);
                    $order->issuance()->create([
                        'trace_id' => (string) Str::uuid(), 'status' => 'pending', 'available_at' => now(),
                    ]);
                    app(NotificationCenter::class)->order($order, 'paid');
                    $outcome = 'paid';
                }
            }
            $event->update(['outcome' => $outcome]);
            Audit::record('payment.'.$outcome, null, $order->id, [], $data['event_id']);

            return $outcome;
        }, 5);
    }
}
