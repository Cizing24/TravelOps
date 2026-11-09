<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VenueResource;
use Filament\Resources\Pages\ListRecords;

final class ListVenue extends ListRecords
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [VenueResource::editor(false)];
    }
}
