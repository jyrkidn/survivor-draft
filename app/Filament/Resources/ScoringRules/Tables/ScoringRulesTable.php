<?php

namespace App\Filament\Resources\ScoringRules\Tables;

use App\Enums\EventType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScoringRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('points', 'desc')
            ->columns([
                TextColumn::make('season.number')->label('Season')->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (EventType $state): string => $state->label()),
                TextColumn::make('points')->numeric()->sortable(),
                TextColumn::make('label')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Season')
                    ->relationship('season', 'number')
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
