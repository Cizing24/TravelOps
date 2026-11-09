<?php

namespace App\Filament\Resources;

use App\Application\CatalogAdmin;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListSupplier;
use App\Infrastructure\MockSupplier;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class SupplierResource extends OperationsResource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationLabel = '供應商';

    protected static ?string $modelLabel = '供應商';

    protected static ?string $pluralModelLabel = '供應商';

    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('供應商')->searchable()->sortable(),
            TextColumn::make('scenario')->label('開券情境')->formatStateUsing(fn (string $state) => MockSupplier::SCENARIOS[$state] ?? $state),
        ])->defaultSort('id', 'desc')->recordActions([
            self::editor(true),
        ]);
    }

    public static function editor(bool $editing): Action
    {
        return self::guardedAction(Action::make($editing ? 'edit' : 'create')->label($editing ? '編輯' : '新增供應商')
            ->schema([
                TextInput::make('name')->label('名稱')->required()->maxLength(160)->unique('suppliers', 'name', ignoreRecord: true),
                Select::make('scenario')->label('開券情境')->options(MockSupplier::SCENARIOS)->default('success')->required()->in(array_keys(MockSupplier::SCENARIOS)),
            ])->fillForm(fn (?Supplier $record) => $record?->only(['name', 'scenario']) ?? ['scenario' => 'success']),
            fn (array $data, ?Supplier $record) => app(CatalogAdmin::class)->save(auth()->user(), $record ?? new Supplier, $data));
    }

    public static function getPages(): array
    {
        return ['index' => ListSupplier::route('/')];
    }
}
