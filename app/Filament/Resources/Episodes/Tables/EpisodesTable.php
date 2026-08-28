<?php

namespace App\Filament\Resources\Episodes\Tables;

use App\Models\Episode;
use App\Models\Event;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EpisodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('number', 'desc')
            ->columns([
                TextColumn::make('season.number')->label('Season')->sortable()->toggleable(),
                TextColumn::make('number')->label('Ep')->numeric()->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('air_date')->date()->sortable(),
                TextColumn::make('events')
                    ->label('Events')
                    ->state(fn (Episode $record): int => Event::query()
                        ->where('season_id', $record->season_id)
                        ->where('episode_number', $record->number)
                        ->count()),
                TextColumn::make('notes')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Season')
                    ->relationship('season', 'number')
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
