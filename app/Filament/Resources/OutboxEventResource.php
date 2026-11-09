<?php

namespace App\Filament\Resources;

use App\Application\Issuance;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListOutboxEvent;
use App\Models\OutboxEvent;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class OutboxEventResource extends OperationsResource
{
    protected static ?string $model = OutboxEvent::class;

    protected static ?string $navigationLabel = '開券與異常';

    protected static ?string $modelLabel = '開券與異常';

    protected static ?string $pluralModelLabel = '開券與異常';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.reference')->label('訂單編號')->searchable(),
            TextColumn::make('trace_id')->label('追蹤編號')->searchable()->copyable(),
            self::status(),
            TextColumn::make('attempts')->label('嘗試次數')->sortable(),
            TextColumn::make('last_error')->label('最後錯誤')->wrap(),
            TextColumn::make('available_at')->label('可執行時間')->dateTime(timezone: 'Asia/Taipei')->sortable(),
        ])->defaultSort('id', 'desc')->filters([self::statusFilter(['pending', 'succeeded', 'retryable_failed', 'permanently_failed', 'cancelled'])])->recordActions([
            ViewAction::make()->label('嘗試明細')->schema([
                TextEntry::make('trace_id')->label('追蹤編號'),
                TextEntry::make('history.result')->label('歷次結果')->listWithLineBreaks(),
                TextEntry::make('history.error')->label('歷次錯誤')->listWithLineBreaks(),
                TextEntry::make('last_error')->label('最後錯誤'),
            ]),
            self::guardedAction(Action::make('retry')->label('重新排程')->requiresConfirmation()
                ->visible(fn (OutboxEvent $record) => $record->status === 'permanently_failed'),
                fn (array $data, OutboxEvent $record) => app(Issuance::class)->requeue(auth()->user(), $record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOutboxEvent::route('/')];
    }
}
