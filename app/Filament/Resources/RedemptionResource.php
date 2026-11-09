<?php

namespace App\Filament\Resources;

use App\Filament\OperationsResource;
use App\Filament\Pages\ListRedemption;
use App\Models\Redemption;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class RedemptionResource extends OperationsResource
{
    protected static ?string $model = Redemption::class;

    protected static ?string $navigationLabel = '核銷紀錄';

    protected static ?string $modelLabel = '核銷紀錄';

    protected static ?string $pluralModelLabel = '核銷紀錄';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('venue.name')->label('場館')->searchable(),
            TextColumn::make('user.name')->label('場館人員'),
            TextColumn::make('trace_id')->label('追蹤編號')->searchable()->copyable(),
            TextColumn::make('redeemed_at')->label('核銷時間')->dateTime(timezone: 'Asia/Taipei')->sortable(),
        ])->defaultSort('id', 'desc')->filters([SelectFilter::make('venue')->label('場館')->relationship('venue', 'name')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRedemption::route('/')];
    }
}
