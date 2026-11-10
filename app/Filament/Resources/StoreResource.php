<?php

namespace App\Filament\Resources;

use App\Application\CatalogAdmin;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListStore;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class StoreResource extends OperationsResource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationLabel = '門市';

    protected static ?string $pluralModelLabel = '門市';

    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('門市')->searchable()])
            ->recordActions([self::editor(true)]);
    }

    public static function editor(bool $editing): Action
    {
        return self::guardedAction(Action::make($editing ? 'edit' : 'create')->label($editing ? '編輯' : '新增門市')
            ->schema([TextInput::make('name')->label('門市名稱')->required()->maxLength(160)->unique('stores', 'name', ignoreRecord: true)])
            ->fillForm(fn (?Store $record) => $record?->only(['name']) ?? []),
            fn (array $data, ?Store $record) => app(CatalogAdmin::class)->save(auth()->user(), $record ?? new Store, $data));
    }

    public static function getPages(): array
    {
        return ['index' => ListStore::route('/')];
    }
}
