<?php

namespace App\Filament\Resources\Managers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManagersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('draft_slot')
            ->columns([
                TextColumn::make('season.number')->label('Season')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('draft_slot')->label('Slot')->numeric()->sortable(),
                TextColumn::make('draft_picks_count')
                    ->label('Picks made')
                    ->counts('draftPicks'),
                TextColumn::make('users.email')
                    ->label('Logins')
                    ->badge()
                    ->placeholder('none linked'),
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
