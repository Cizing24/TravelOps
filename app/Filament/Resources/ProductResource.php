<?php

namespace App\Filament\Resources;

use App\Application\CatalogAdmin;
use App\Domain\Money;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListProduct;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class ProductResource extends OperationsResource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationLabel = '商品與權益';

    protected static ?string $modelLabel = '商品與權益';

    protected static ?string $pluralModelLabel = '商品與權益';

    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('商品')->searchable()->sortable(),
            TextColumn::make('slug')->label('網址識別碼')->searchable()->toggleable(),
            self::status('kind', '票種'),
            TextColumn::make('price')->label('售價')->formatStateUsing(fn (int $state) => Money::twd($state))->sortable(),
            TextColumn::make('inventory.available')->label('可售庫存')->sortable(),
            TextColumn::make('venues.name')->label('場館')->listWithLineBreaks()->limitList(3),
            IconColumn::make('listed')->label('上架')->boolean(),
        ])->defaultSort('id', 'desc')->filters([TernaryFilter::make('listed')->label('已上架')])->recordActions([
            self::editor(true),
            self::guardedAction(Action::make('stock')->label('調整庫存')->schema([
                TextInput::make('delta')->label('增加或減少的數量')->integer()->minValue(-1000000)->maxValue(1000000)->required(),
            ]), fn (array $data, Product $record) => app(CatalogAdmin::class)->adjustStock(auth()->user(), $record, (int) $data['delta'])),
        ]);
    }

    public static function editor(bool $editing): Action
    {
        $fields = [
            TextInput::make('name')->label('商品名稱')->required()->maxLength(160),
            TextInput::make('slug')->label('網址識別碼')->required()->alphaDash()->maxLength(160)->unique('products', 'slug', ignoreRecord: true),
            Select::make('supplier_id')->label('開券供應商')->options(fn () => Supplier::orderBy('name')->pluck('name', 'id'))
                ->exists('suppliers', 'id')->searchable()->required(),
            Select::make('kind')->label('票種')->options(['single' => '單館票', 'bundle' => '聯票'])->required(),
            Textarea::make('description')->label('商品描述')->required()->maxLength(2500)->columnSpanFull(),
            TextInput::make('price')->label('價格（新臺幣分）')->integer()->minValue(1)->maxValue(100000000)->required(),
            TextInput::make('valid_days')->label('首次核銷後有效天數')->integer()->minValue(1)->maxValue(365)->default(14)->required(),
            Select::make('venue_ids')->label('場館權益')->multiple()->options(fn () => Venue::orderBy('name')->pluck('name', 'id'))
                ->searchable()->required(),
            Toggle::make('listed')->label('上架')->default(false),
        ];
        if (! $editing) {
            $fields[] = TextInput::make('available')->label('初始庫存')->integer()->minValue(0)->maxValue(1000000)->required();
        }

        return self::guardedAction(Action::make($editing ? 'edit' : 'create')->label($editing ? '編輯' : '新增商品')
            ->schema($fields)->fillForm(fn (?Product $record) => $record === null ? ['valid_days' => 14, 'listed' => false] :
                $record->only(['name', 'slug', 'supplier_id', 'kind', 'description', 'price', 'valid_days', 'listed'])
                + ['venue_ids' => $record->venues()->allRelatedIds()->all()]),
            fn (array $data, ?Product $record) => app(CatalogAdmin::class)->product(auth()->user(), $data, $record));
    }

    public static function getPages(): array
    {
        return ['index' => ListProduct::route('/')];
    }
}
