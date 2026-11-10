<?php

namespace App\Filament\Pages;

use App\Application\OperationsReport;
use App\Models\Store;
use App\Models\Supplier;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

final class Reports extends Page
{
    protected string $view = 'filament.reports';

    protected static ?string $navigationLabel = '營運報表';

    protected static ?string $title = '營運報表';

    public static function canAccess(): bool
    {
        return Gate::allows('operate');
    }

    protected function getViewData(): array
    {
        return app(OperationsReport::class)->summarize(request()->query()) + [
            'stores' => Store::orderBy('name')->get(), 'suppliers' => Supplier::orderBy('name')->get(),
        ];
    }
}
