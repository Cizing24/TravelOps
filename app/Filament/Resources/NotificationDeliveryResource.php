<?php

namespace App\Filament\Resources;

use App\Application\NotificationCenter;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListNotificationDelivery;
use App\Models\NotificationDelivery;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class NotificationDeliveryResource extends OperationsResource
{
    protected static ?string $model = NotificationDelivery::class;

    protected static ?string $navigationLabel = '通知投遞';

    protected static ?string $pluralModelLabel = '通知投遞';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('message.title')->label('通知')->searchable(),
            TextColumn::make('message.user_id')->label('內部收件人'),
            TextColumn::make('channel')->label('模擬通道'),
            self::status(),
            TextColumn::make('attempts')->label('嘗試次數'),
            TextColumn::make('last_error')->label('錯誤代碼'),
            TextColumn::make('available_at')->label('下次嘗試')->dateTime(timezone: 'Asia/Taipei'),
        ])->poll('5s')->defaultSort('id', 'desc')->filters([
            self::statusFilter(['queued', 'sent', 'failed', 'suppressed']),
        ])->recordActions([
            self::guardedAction(Action::make('retry')->label('重新排程通知')->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('原因')->required()->maxLength(500)])
                ->visible(fn (NotificationDelivery $record) => $record->status === 'failed' && $record->available_at === null),
                fn (array $data, NotificationDelivery $record) => app(NotificationCenter::class)->requeue(auth()->user(), $record, $data['reason'])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNotificationDelivery::route('/')];
    }
}
