<?php

namespace App\Filament\Resources\Managers\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ManagerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->relationship('season', 'number')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')->required(),
                TextInput::make('draft_slot')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Slot 1 picks the odd numbers, slot 2 the even ones. '
                        .'Prefer set_draft_order over editing this by hand.'),
                Select::make('users')
                    ->label('Logins that play as this manager')
                    ->relationship('users', 'email')
                    ->multiple()
                    ->preload()
                    ->options(fn (): array => User::query()->pluck('email', 'id')->all())
                    ->helperText('Without this link a login can see the draft but cannot pick.'),
            ]);
    }
}
