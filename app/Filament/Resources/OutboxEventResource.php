<?php

namespace App\Filament\Resources;

use App\Application\Issuance;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListOutboxEvent;
use App\Models\OutboxEvent;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
            TextColumn::make('order.item.supplier.name')->label('供應商')->searchable(),
            TextColumn::make('trace_id')->label('追蹤編號')->searchable()->copyable(),
            self::status(),
            TextColumn::make('attempts')->label('嘗試次數')->sortable(),
            TextColumn::make('last_error')->label('最後錯誤')->wrap(),
            TextColumn::make('available_at')->label('可執行時間')->dateTime(timezone: 'Asia/Taipei')->sortable(),
        ])->defaultSort('id', 'desc')->filters([
            self::statusFilter(['pending', 'awaiting_callback', 'succeeded', 'retryable_failed', 'permanently_failed', 'cancelled']),
            SelectFilter::make('supplier')->label('供應商')->options(fn () => Supplier::orderBy('name')->pluck('name', 'id'))
                ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                    fn (Builder $query, mixed $id) => $query->whereRelation('order.item', 'supplier_id', $id))),
            Filter::make('created_on')->label('建立日期（臺灣時間）')
                ->schema([DatePicker::make('day')->label('建立日期（臺灣時間）')])
                ->query(function (Builder $query, array $data): Builder {
                    if (empty($data['day'])) {
                        return $query;
                    }
                    $start = Carbon::parse($data['day'], 'Asia/Taipei')->startOfDay();

                    return $query->where('created_at', '>=', $start->copy()->utc())
                        ->where('created_at', '<', $start->addDay()->utc());
                }),
        ])->recordActions([
            ViewAction::make()->label('嘗試明細')->schema([
                TextEntry::make('trace_id')->label('追蹤編號'),
                TextEntry::make('history.result')->label('歷次結果')->listWithLineBreaks(),
                TextEntry::make('history.error')->label('歷次錯誤')->listWithLineBreaks(),
                TextEntry::make('last_error')->label('最後錯誤'),
                TextEntry::make('cancelled_at')->label('供應商確認撤銷時間')->dateTime(timezone: 'Asia/Taipei'),
                TextEntry::make('cancel_available_at')->label('下次撤銷確認時間')->dateTime(timezone: 'Asia/Taipei'),
                TextEntry::make('cancel_error')->label('撤銷錯誤代碼'),
            ]),
            self::guardedAction(Action::make('retry')->label('重新排程')->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('重新排程原因')->required()->maxLength(500)])
                ->visible(fn (OutboxEvent $record) => $record->status === 'permanently_failed'),
                fn (array $data, OutboxEvent $record) => app(Issuance::class)->requeue(auth()->user(), $record, $data['reason'])),
            self::guardedAction(Action::make('retryCancellation')->label('重新排程撤銷')->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('重新排程原因')->required()->maxLength(500)])
                ->visible(fn (OutboxEvent $record) => $record->cancel_error !== null && $record->cancel_available_at === null),
                fn (array $data, OutboxEvent $record) => app(Issuance::class)->requeueCancellation(auth()->user(), $record, $data['reason'])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOutboxEvent::route('/')];
    }
}
