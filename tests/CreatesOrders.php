<?php

namespace Tests;

use App\Application\Checkout;
use App\Application\Issuance;
use App\Application\PaymentWebhook;
use App\Infrastructure\MockGateway;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesOrders
{
    private function place(?Product $product = null, ?User $customer = null, int $quantity = 1): Order
    {
        $product ??= Product::factory()->stocked()->create();
        $customer ??= User::factory()->create();

        return app(Checkout::class)->place($customer, $product->id, $quantity, (string) Str::uuid());
    }

    private function pay(Order $order): void
    {
        $event = app(MockGateway::class)->event($order, 'succeeded');
        app(PaymentWebhook::class)->receive($event['body'], $event['signature']);
    }

    private function issue(Order $order): void
    {
        app(Issuance::class)->process($order->issuance()->firstOrFail()->id);
    }
}
