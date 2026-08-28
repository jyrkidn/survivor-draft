<?php

namespace App\Filament\Resources\ScoringRules\Schemas;

use App\Enums\EventType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ScoringRuleForm
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
                Select::make('type')
                    ->required()
                    ->options(collect(EventType::cases())
                        ->mapWithKeys(fn (EventType $type): array => [$type->value => $type->label()])
                        ->all()),
                TextInput::make('points')
                    ->required()
                    ->numeric()
                    ->helperText('Changing this re-scores the whole season immediately, because no total is '
                        .'stored anywhere.'),
                TextInput::make('label'),
            ]);
    }
}
