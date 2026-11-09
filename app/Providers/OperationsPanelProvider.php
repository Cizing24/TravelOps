<?php

namespace App\Providers;

use App\Filament\Pages\Reconciliation;
use App\Filament\Pages\Reports;
use App\Filament\Widgets\OperationsStats;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class OperationsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()->id('operations')->path('ops')->login()->brandName('TravelOps 營運台')
            ->colors(['primary' => Color::Emerald])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->pages([Dashboard::class, Reconciliation::class, Reports::class])->widgets([OperationsStats::class])
            ->middleware([
                EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
                AuthenticateSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
                SubstituteBindings::class, DisableBladeIconComponents::class, DispatchServingFilamentEvent::class,
            ])->authMiddleware([Authenticate::class]);
    }
}
