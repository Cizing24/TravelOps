<?php

use App\Application\Checkout;
use App\Application\Issuance;
use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('travelops:relay', function (): void {
    $this->info('Queued: '.app(Issuance::class)->relay());
});

Artisan::command('travelops:expire', function (): void {
    $checkout = app(Checkout::class);
    $expired = 0;
    Order::where('status', 'pending_payment')->where('pay_by', '<=', now())->chunkById(100, function ($orders) use ($checkout, &$expired): void {
        foreach ($orders as $order) {
            $expired += (int) $checkout->expire($order->id);
        }
    });
    $this->info('Expired: '.$expired);
});

Schedule::command('travelops:relay')->everyTenSeconds();
Schedule::command('travelops:expire')->everyMinute();
