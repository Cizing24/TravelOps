<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Resources\Pages\ListRecords;

final class ListStore extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [StoreResource::editor(false)];
    }
}
