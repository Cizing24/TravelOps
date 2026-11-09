<?php

namespace App\Filament\Resources;

use App\Application\RefundOrder;
use App\Domain\Money;
use App\Domain\OrderState;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListOrder;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class OrderResource extends OperationsResource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationLabel = '訂單';

    protected static ?string $modelLabel = '訂單';

    protected static ?string $pluralModelLabel = '訂單';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->label('訂單編號')->searchable()->copyable(),
            TextColumn::make('item.name')->label('商品'),
            TextColumn::make('item.quantity')->label('數量'),
            self::status(),
            self::status('payment.status', '付款'),
            self::status('issuance.status', '開券'),
            TextColumn::make('total')->label('總額')->formatStateUsing(fn (int $state) => Money::twd($state))->sortable(),
            TextColumn::make('created_at')->label('下單時間')->dateTime(timezone: 'Asia/Taipei')->sortable(),
        ])->defaultSort('id', 'desc')->filters([self::statusFilter(['pending_payment', 'paid', 'fulfilling', 'fulfilled', 'expired', 'cancelled', 'refunded'])])->recordActions([
            ViewAction::make()->label('交易紀錄')->schema([
                TextEntry::make('reference')->label('訂單編號'),
                TextEntry::make('payment.status')->label('付款結果')->formatStateUsing(fn (string $state) => __('status.'.$state)),
                TextEntry::make('payment.paid_at')->label('付款時間')->dateTime(timezone: 'Asia/Taipei'),
                TextEntry::make('webhookEvents.outcome')->label('回呼結果')->listWithLineBreaks(),
                TextEntry::make('issuance.trace_id')->label('開券追蹤編號'),
                TextEntry::make('issuance.attempts')->label('開券嘗試次數'),
                TextEntry::make('issuance.last_error')->label('最後錯誤'),
            ]),
            self::guardedAction(Action::make('requestRefund')->label('建立退款申請')->requiresConfirmation()
                ->visible(fn (Order $record) => OrderState::captured($record->status) && $record->refund === null),
                fn (array $data, Order $record) => app(RefundOrder::class)->request(auth()->user(), $record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOrder::route('/')];
    }
}
