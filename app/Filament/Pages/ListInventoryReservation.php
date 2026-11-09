<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryReservationResource;
use Filament\Resources\Pages\ListRecords;

final class ListInventoryReservation extends ListRecords
{
    protected static string $resource = InventoryReservationResource::class;
}
