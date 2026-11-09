<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Resources\Pages\ListRecords;

final class ListSupplier extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [SupplierResource::editor(false)];
    }
}
