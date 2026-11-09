<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListAuditLog extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
