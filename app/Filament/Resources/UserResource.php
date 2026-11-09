<?php

namespace App\Filament\Resources;

use App\Application\CatalogAdmin;
use App\Filament\OperationsResource;
use App\Filament\Pages\ListUser;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class UserResource extends OperationsResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = '帳號與場館授權';

    protected static ?string $modelLabel = '帳號與場館授權';

    protected static ?string $pluralModelLabel = '帳號與場館授權';

    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('姓名')->searchable(),
            TextColumn::make('email')->label('Email')->searchable(),
            self::status('role', '角色'),
            TextColumn::make('venues.name')->label('授權場館')->listWithLineBreaks()->limitList(3),
        ])->defaultSort('id', 'desc')->recordActions([
            self::editor(true),
        ]);
    }

    public static function editor(bool $editing): Action
    {
        $roles = ['customer' => '旅客', 'merchant' => '場館人員', 'operator' => '營運人員', 'admin' => '管理員'];

        return self::guardedAction(Action::make($editing ? 'edit' : 'create')->label($editing ? '編輯' : '新增帳號')
            ->visible(fn (?User $record) => $record === null || $record->id !== auth()->id())
            ->schema([
                TextInput::make('name')->label('姓名')->required()->maxLength(160),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(254)->unique('users', 'email', ignoreRecord: true),
                TextInput::make('password')->label($editing ? '新密碼（留白保留）' : '密碼')
                    ->password()->minLength(12)->maxLength(72)->required(! $editing),
                Select::make('role')->label('角色')->options($roles)->required()->in(array_keys($roles)),
                Select::make('venue_ids')->label('場館授權（限場館人員）')->multiple()
                    ->options(fn () => Venue::orderBy('name')->pluck('name', 'id'))->searchable(),
            ])->fillForm(fn (?User $record) => $record === null ? [] :
                $record->only(['name', 'email', 'role']) + ['venue_ids' => $record->venues()->allRelatedIds()->all()]),
            fn (array $data, ?User $record) => app(CatalogAdmin::class)->user(auth()->user(), $data, $record));
    }

    public static function getPages(): array
    {
        return ['index' => ListUser::route('/')];
    }
}
