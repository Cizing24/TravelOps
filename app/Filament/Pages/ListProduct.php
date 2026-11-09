<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\ListRecords;

final class ListProduct extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [ProductResource::editor(false)];
    }
}
