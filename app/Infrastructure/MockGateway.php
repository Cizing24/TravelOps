<?php

namespace App\Infrastructure;

use App\Models\Order;
use Illuminate\Support\Str;

final class MockGateway
{
    public function enabled(): bool
    {
        return app()->environment('local', 'testing')
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
        $body = json_encode([
            'event_id' => (string) Str::uuid(), 'order' => $order->reference,
            'payment' => $order->payment?->idempotency_key, 'status' => $status,
            'amount' => $order->total, 'currency' => $order->currency,
        ], JSON_THROW_ON_ERROR);

        return ['body' => $body, 'signature' => $this->signature($body)];
    }
}
