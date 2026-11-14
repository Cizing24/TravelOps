<?php

namespace App\Infrastructure;

use App\Models\Order;
use App\Models\PaymentStatement;
use Illuminate\Support\Str;

final class MockGateway
{
    public function enabled(): bool
    {
        $publicDemo = app()->environment('staging')
            && config('travelops.public_demo') === true
            && config('app.debug') === false
            && str_starts_with((string) config('app.url'), 'https://')
            && config('database.connections.mysql.database') === 'travelops_public_demo';

        return (app()->environment('local', 'testing') || $publicDemo)
            && config('travelops.demo') === true
            && strlen((string) config('travelops.webhook_secret')) >= 32;
    }

    public function signature(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('travelops.webhook_secret'));
    }

    /** @return array{body: string, signature: string} */
    public function event(Order $order, string $status): array
    {
        $payment = $order->payment;
        if ($status === 'succeeded' && $payment !== null && $this->enabled()) {
            PaymentStatement::query()->createOrFirst(['request_key' => $payment->idempotency_key], [
                'order_reference' => $order->reference, 'amount' => $payment->amount,
            ]);
        }
        $body = json_encode([
            'event_id' => (string) Str::uuid(), 'order' => $order->reference,
            'payment' => $payment?->idempotency_key, 'status' => $status,
            'amount' => $order->total, 'currency' => $order->currency,
        ], JSON_THROW_ON_ERROR);

        return ['body' => $body, 'signature' => $this->signature($body)];
    }
}
