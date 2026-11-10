<?php

namespace App\Application;

use App\Infrastructure\MockGateway;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PointOfSale
{
    public function __construct(
        private Checkout $checkout,
        private MockGateway $gateway,
        private PaymentWebhook $payment,
    ) {}

    public function sell(User $actor, Store $store, int $productId, int $quantity, string $key, string $method): Order
    {
        abort_unless($this->gateway->enabled(), 404);

        return DB::transaction(function () use ($actor, $store, $productId, $quantity, $key, $method): Order {
            $order = $this->checkout->place($actor, $productId, $quantity, $key, $store, $method);
            if ($order->wasRecentlyCreated) {
                $event = $this->gateway->event($order, 'succeeded');
                $this->payment->receive($event['body'], $event['signature']);
                $order->refresh();
            }

            return $order;
        }, 5);
    }
}
