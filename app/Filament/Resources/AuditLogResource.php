<?php

namespace App\Filament\Resources;

use App\Filament\OperationsResource;
use App\Filament\Pages\ListAuditLog;
use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AuditLogResource extends OperationsResource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationLabel = '稽核紀錄';

    protected static ?string $modelLabel = '稽核紀錄';

    protected static ?string $pluralModelLabel = '稽核紀錄';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('action')->label('操作')->searchable(),
            TextColumn::make('order.reference')->label('訂單編號')->searchable(),
            TextColumn::make('trace_id')->label('追蹤編號')->searchable()->copyable(),
            TextColumn::make('user.name')->label('操作者')->placeholder('系統'),
            TextColumn::make('created_at')->label('時間')->dateTime(timezone: 'Asia/Taipei')->sortable(),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLog::route('/')];
    }
}
