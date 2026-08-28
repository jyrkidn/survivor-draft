<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EventForm
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
                Select::make('castaway_id')
                    ->relationship('castaway', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('episode_number')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Select::make('type')
                    ->required()
                    ->options(collect(EventType::storable())
                        ->mapWithKeys(fn (EventType $type): array => [$type->value => $type->label()])
                        ->all())
                    ->helperText('"Survived" is missing on purpose: survival points are derived from the '
                        .'eliminations and the episode count, and can never be recorded by hand.'),
                TextInput::make('points')
                    ->label('Points override')
                    ->numeric()
                    ->helperText('Leave blank to use the season\'s scoring rule. This overrides it for this one '
                        .'event only — it is not a total.'),
                Textarea::make('note')->rows(2)->columnSpanFull(),
            ]);
    }
}
