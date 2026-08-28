<?php

namespace App\Filament\Resources\Seasons\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SeasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->label('Season number')
                    ->required()
                    ->numeric()
                    ->unique(ignoreRecord: true),
                TextInput::make('title')
                    ->placeholder('The Open Era'),
                DatePicker::make('premiere_date'),
                TextInput::make('roster_size')
                    ->label('Cast size')
                    ->helperText('Sets the draft length: 21 castaways over 2 managers gives 10 rounds and 20 picks.')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active season')
                    ->helperText('The season every MCP tool defaults to.'),
            ]);
    }
}
