<?php

use App\Application\Checkout;
use App\Application\Issuance;
use App\Application\NotificationCenter;
use App\Application\Reconcile;
use App\Application\RefundOrder;
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

Artisan::command('travelops:reconcile {day?}', function (): void {
    $day = (string) ($this->argument('day') ?? now('Asia/Taipei')->subDay()->toDateString());
    $run = app(Reconcile::class)->run($day, null, null);
    $this->info('Findings: '.$run->anomalies);
});

Schedule::command('travelops:relay')->everyTenSeconds();
Schedule::command('travelops:expire')->everyMinute();
Schedule::call(fn () => app(RefundOrder::class)->relay())->name('travelops:refund-relay')->everyTenSeconds();
Schedule::command('travelops:reconcile')->dailyAt('02:00')->timezone('Asia/Taipei')->when(fn (): bool => app()->environment('local'));
Schedule::call(fn () => app(NotificationCenter::class)->relay())->name('travelops:notification-relay')->everyTenSeconds();
Schedule::call(fn () => app(NotificationCenter::class)->remind())->name('travelops:notification-reminders')->hourly();
