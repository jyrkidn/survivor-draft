<?php

namespace App\Filament\Resources\Episodes\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EpisodeForm
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
                TextInput::make('number')
                    ->label('Episode number')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->helperText('An episode row is what makes survival points count. '
                        .'Use "Record episode" to enter results.'),
                TextInput::make('title'),
                DatePicker::make('air_date'),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]);
    }
}
