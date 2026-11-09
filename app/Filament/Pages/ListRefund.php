<?php

namespace App\Filament\Pages;

use App\Filament\Resources\RefundResource;
use Filament\Resources\Pages\ListRecords;

final class ListRefund extends ListRecords
{
    protected static string $resource = RefundResource::class;
}
