<?php

namespace App\Filament\Pages;

use App\Filament\Resources\RedemptionResource;
use Filament\Resources\Pages\ListRecords;

final class ListRedemption extends ListRecords
{
    protected static string $resource = RedemptionResource::class;
}
