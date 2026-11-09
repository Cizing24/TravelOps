<?php

namespace App\Filament\Resources;

use App\Filament\OperationsResource;
use App\Filament\Pages\ListTicket;
use App\Models\Ticket;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TicketResource extends OperationsResource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationLabel = '票券';

    protected static ?string $modelLabel = '票券';

    protected static ?string $pluralModelLabel = '票券';

    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.reference')->label('訂單編號')->searchable(),
            TextColumn::make('sequence')->label('票券序號'),
            self::status(),
            TextColumn::make('entitlements.venue_name')->label('場館')->listWithLineBreaks()->limitList(3),
            TextColumn::make('expires_at')->label('到期時間')->dateTime(timezone: 'Asia/Taipei')->sortable(),
        ])->defaultSort('id', 'desc')->filters([self::statusFilter(['active', 'revoked'])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTicket::route('/')];
    }
}
