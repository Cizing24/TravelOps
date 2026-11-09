<?php

namespace App\Filament\Resources;

use App\Application\CatalogAdmin;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListVenue;
use App\Models\Supplier;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VenueResource extends OperationsResource
{
    protected static ?string $model = Venue::class;

    protected static ?string $navigationLabel = '場館';

    protected static ?string $modelLabel = '場館';

    protected static ?string $pluralModelLabel = '場館';

    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('場館')->searchable()->sortable(),
            TextColumn::make('supplier.name')->label('供應商')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([
            self::editor(true),
        ]);
    }

    public static function editor(bool $editing): Action
    {
        return self::guardedAction(Action::make($editing ? 'edit' : 'create')->label($editing ? '編輯' : '新增場館')
            ->schema([
                TextInput::make('name')->label('場館名稱')->required()->maxLength(160)->unique('venues', 'name', ignoreRecord: true),
                Select::make('supplier_id')->label('供應商')->options(fn () => Supplier::orderBy('name')->pluck('name', 'id'))
                    ->exists('suppliers', 'id')->searchable()->required(),
            ])->fillForm(fn (?Venue $record) => $record?->only(['name', 'supplier_id']) ?? []),
            fn (array $data, ?Venue $record) => app(CatalogAdmin::class)->save(auth()->user(), $record ?? new Venue, $data));
    }

    public static function getPages(): array
    {
        return ['index' => ListVenue::route('/')];
    }
}
