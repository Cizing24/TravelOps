<?php

namespace App\Filament\Resources;

use App\Application\RefundOrder;
use App\Domain\Money;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListRefund;
use App\Models\Refund;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class RefundResource extends OperationsResource
{
    protected static ?string $model = Refund::class;

    protected static ?string $navigationLabel = '退款審核';

    protected static ?string $modelLabel = '退款審核';

    protected static ?string $pluralModelLabel = '退款審核';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.reference')->label('訂單編號')->searchable(),
            self::status(),
            TextColumn::make('amount')->label('金額')->formatStateUsing(fn (int $state) => Money::twd($state)),
            TextColumn::make('reason')->label('原因')->wrap(),
            TextColumn::make('reviewer.name')->label('審核人員'),
        ])->defaultSort('id', 'desc')->filters([self::statusFilter(['requested', 'processed', 'rejected'])])->recordActions([
            Action::make('approve')->label('核准模擬退款')->requiresConfirmation()->modalSubmitActionLabel('確認退款')
                ->visible(fn (Refund $record) => $record->status === 'requested')
                ->action(function (Refund $record): void {
                    Gate::authorize('operate');
                    $result = app(RefundOrder::class)->review(auth()->user(), $record, true);
                    if ($result->status === 'rejected') {
                        Notification::make()->title($result->reason)->danger()->send();

                        return;
                    }
                    Notification::make()->title('已完成模擬退款')->success()->send();
                }),
            self::guardedAction(Action::make('reject')->label('拒絕')->color('danger')->requiresConfirmation()
                ->visible(fn (Refund $record) => $record->status === 'requested'),
                fn (array $data, Refund $record) => app(RefundOrder::class)->review(auth()->user(), $record, false)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRefund::route('/')];
    }
}
