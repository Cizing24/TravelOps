<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;

final class ListUser extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [UserResource::editor(false)];
    }
}
