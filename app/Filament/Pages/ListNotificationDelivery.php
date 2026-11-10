<?php

namespace App\Filament\Pages;

use App\Filament\Resources\NotificationDeliveryResource;
use Filament\Resources\Pages\ListRecords;

final class ListNotificationDelivery extends ListRecords
{
    protected static string $resource = NotificationDeliveryResource::class;
}
