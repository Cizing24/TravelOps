<?php

namespace App\Filament;

use App\Domain\RuleViolation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

abstract class OperationsResource extends Resource
{
    protected static bool $adminOnly = false;

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return Gate::inspect(static::$adminOnly ? 'administer' : 'operate');
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return static::getViewAnyAuthorizationResponse();
    }

    protected static function status(string $field = 'status', string $label = '狀態'): TextColumn
    {
        return TextColumn::make($field)->label($label)->badge()
            ->formatStateUsing(fn (string $state): string => __('status.'.$state))
            ->color(fn (string $state): string => match ($state) {
                'paid', 'fulfilled', 'active', 'succeeded', 'processed', 'redeemed' => 'success',
                'permanently_failed', 'failed', 'revoked', 'rejected' => 'danger',
                'pending', 'pending_payment', 'requested', 'retryable_failed', 'fulfilling' => 'warning',
                default => 'gray',
            });
    }

    /** @param list<string> $statuses */
    protected static function statusFilter(array $statuses): SelectFilter
    {
        return SelectFilter::make('status')->label('狀態')->options(
            array_combine($statuses, array_map(fn (string $status): string => __('status.'.$status), $statuses))
        );
    }

    protected static function guardedAction(Action $action, callable $operation): Action
    {
        return $action->action(function (Action $action, array $data, mixed $record) use ($operation): void {
            Gate::authorize(static::$adminOnly ? 'administer' : 'operate');
            try {
                $operation($data, $record);
                Notification::make()->title('操作完成')->success()->send();
            } catch (RuleViolation $error) {
                Notification::make()->title($error->getMessage())->danger()->send();
                $action->halt();
            }
        });
    }
}
