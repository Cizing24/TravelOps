<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OutboxEventResource;
use Filament\Resources\Pages\ListRecords;

final class ListOutboxEvent extends ListRecords
{
    protected static string $resource = OutboxEventResource::class;
}
