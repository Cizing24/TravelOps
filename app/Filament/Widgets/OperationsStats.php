<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\Redemption;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

final class OperationsStats extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return Gate::allows('operate');
    }

    protected function getStats(): array
    {
        Gate::authorize('operate');

        return [
            Stat::make('全部訂單', Order::count()),
            Stat::make('已付款訂單', Payment::where('status', 'succeeded')->count()),
            Stat::make('成功開券', Ticket::count()),
            Stat::make('待處理異常', OutboxEvent::whereIn('status', ['retryable_failed', 'permanently_failed'])->count())->color('warning'),
            Stat::make('核銷紀錄', Redemption::count()),
        ];
    }
}
