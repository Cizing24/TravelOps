<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryMovementResource;
use Filament\Resources\Pages\ListRecords;

final class ListInventoryMovement extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;
}
