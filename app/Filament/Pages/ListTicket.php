<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Resources\Pages\ListRecords;

final class ListTicket extends ListRecords
{
    protected static string $resource = TicketResource::class;
}
