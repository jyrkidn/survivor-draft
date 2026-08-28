<?php

namespace App\Filament\Resources\Seasons\Tables;

use App\Models\Season;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeasonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['castaways', 'episodes', 'draftPicks']))
            ->defaultSort('number', 'desc')
            ->columns([
                TextColumn::make('number')->numeric()->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('premiere_date')->date()->sortable(),
                TextColumn::make('roster_size')->label('Cast')->numeric(),
                TextColumn::make('castaways_count')->label('Entered')->numeric(),
                TextColumn::make('draft_picks_count')->label('Picks')->numeric(),
                TextColumn::make('episodes_count')->label('Episodes aired')->numeric(),
                TextColumn::make('draft_rounds')
                    ->label('Rounds')
                    ->state(fn (Season $record): int => $record->draftRounds()),
                IconColumn::make('is_active')->label('Active')->boolean(),
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
