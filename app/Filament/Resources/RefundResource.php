<?php

namespace App\Filament\Resources;

use App\Application\RefundOrder;
use App\Domain\Money;
use App\Domain\RuleViolation;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListRefund;
use App\Infrastructure\MockRefundGateway;
use App\Models\Refund;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
            TextColumn::make('attempts')->label('嘗試次數'),
        ])->poll('5s')->defaultSort('id', 'desc')->filters([
            self::statusFilter(['requested', 'approved', 'submitted', 'pending', 'processed', 'failed', 'requires_review', 'rejected']),
        ])->recordActions([
            Action::make('reconciliation')->label('對帳差異')->url(fn (Refund $record): string => url('/ops/reconciliation').'?'.http_build_query([
                'reference' => $record->order->reference, 'day' => $record->created_at?->timezone('Asia/Taipei')->toDateString(),
            ])),
            Action::make('approve')->label('核准模擬退款')->requiresConfirmation()->modalSubmitActionLabel('確認退款')
                ->schema([Select::make('scenario')->label('模擬退款情境')->options(MockRefundGateway::SCENARIOS)->default('success')->required()])
                ->visible(fn (Refund $record) => $record->status === 'requested')
                ->action(function (Action $action, array $data, Refund $record): void {
                    Gate::authorize('operate');
                    try {
                        $result = app(RefundOrder::class)->review(auth()->user(), $record, true, $data['scenario']);
                    } catch (RuleViolation $error) {
                        Notification::make()->title($error->getMessage())->danger()->send();
                        $action->halt();

                        return;
                    }
                    if ($result->status === 'rejected') {
                        Notification::make()->title($result->reason)->danger()->send();

                        return;
                    }
                    Notification::make()->title('退款已排程，請等待模擬金流確認')->success()->send();
                }),
            self::guardedAction(Action::make('retry')->label('重新排程退款')->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('原因')->required()->maxLength(255)])
                ->visible(fn (Refund $record) => $record->status === 'requires_review'),
                fn (array $data, Refund $record) => app(RefundOrder::class)->requeue(auth()->user(), $record, $data['reason'])),
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
