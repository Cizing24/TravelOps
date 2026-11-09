<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;

final class ListOrder extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
