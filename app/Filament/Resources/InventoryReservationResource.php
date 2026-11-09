<?php

namespace App\Filament\Resources;

use App\Filament\OperationsResource;
use App\Filament\Pages\ListInventoryReservation;
use App\Models\InventoryReservation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class InventoryReservationResource extends OperationsResource
{
    protected static ?string $model = InventoryReservation::class;

    protected static ?string $navigationLabel = '庫存預留';

    protected static ?string $modelLabel = '庫存預留';

    protected static ?string $pluralModelLabel = '庫存預留';

    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.reference')->label('訂單編號')->searchable(),
            TextColumn::make('product.name')->label('商品')->searchable(),
            TextColumn::make('quantity')->label('數量')->sortable(),
            self::status(),
            TextColumn::make('order.pay_by')->label('付款期限')->dateTime(timezone: 'Asia/Taipei'),
        ])->defaultSort('id', 'desc')->filters([self::statusFilter(['held', 'sold', 'released', 'returned'])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryReservation::route('/')];
    }
}
