<?php

namespace App\Filament\Resources;

use App\Filament\OperationsResource;
use App\Filament\Pages\ListInventoryMovement;
use App\Models\InventoryMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InventoryMovementResource extends OperationsResource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationLabel = '庫存流水';

    protected static ?string $pluralModelLabel = '庫存流水';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('product.name')->label('商品')->searchable(),
            TextColumn::make('product.inventory.available')->label('目前可售量'),
            self::status('kind', '異動'),
            TextColumn::make('delta')->label('可售量差額'),
            TextColumn::make('reason')->label('原因')->wrap(),
            TextColumn::make('order_id')->label('內部訂單'),
            TextColumn::make('user_id')->label('操作人'),
            TextColumn::make('created_at')->label('時間')->dateTime(timezone: 'Asia/Taipei'),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('product')->label('商品')->relationship('product', 'name')->searchable(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryMovement::route('/')];
    }
}
