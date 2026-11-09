<?php

namespace App\Providers;

use App\Application\SupplierProvider;
use App\Infrastructure\MockSupplier;
use App\Models\User;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SupplierProvider::class, MockSupplier::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('components.pagination');
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('SELECT 1');
            Redis::connection()->ping();
        });

        Gate::define('operate', fn (User $user): bool => $user->operates());
        Gate::define('administer', fn (User $user): bool => $user->administers());
        Gate::define('redeem', fn (User $user): bool => $user->role === 'merchant');
    }
}
