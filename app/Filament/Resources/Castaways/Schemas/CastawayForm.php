<?php

namespace App\Filament\Resources\Castaways\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CastawayForm
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
                TextInput::make('nickname')
                    ->helperText('What people actually call them. Used when resolving names during an episode.'),
                TextInput::make('age')->numeric()->minValue(0),
                TextInput::make('occupation'),
                TextInput::make('hometown'),
                TextInput::make('tribe')
                    ->helperText('Lets tribe immunity be recorded by tribe name.'),
                FileUpload::make('photo_path')
                    ->label('Photo')
                    ->image()
                    ->disk('public')
                    ->directory('cast/uploads')
                    ->imageEditor()
                    ->maxSize(5120),
                TextInput::make('photo_source_url')
                    ->label('Photo source URL')
                    ->url()
                    ->helperText('Shown as a fallback when there is no stored file. '
                        .'Use "Fetch photo" on the list to download it.'),
            ]);
    }
}
