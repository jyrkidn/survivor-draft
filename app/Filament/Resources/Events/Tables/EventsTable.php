<?php

namespace App\Filament\Resources\Events\Tables;

use App\Enums\EventType;
use App\Models\Event;
use App\Services\ScoringService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The flat log of everything that has happened.
 *
 * When the scoreboard looks wrong, this is where you find out why: filter down
 * to the castaway or the episode, spot the event that should not be there, and
 * delete it. Everything downstream re-scores on its own.
 */
class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['castaway', 'season']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('Event')->sortable(),
                TextColumn::make('season.number')->label('Season')->sortable()->toggleable(),
                TextColumn::make('episode_number')->label('Ep')->numeric()->sortable(),
                TextColumn::make('castaway.name')->label('Castaway')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (EventType $state): string => $state->label())
                    ->color(fn (EventType $state): string => match (true) {
                        $state->isElimination() => 'danger',
                        $state->isPlacement() => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('worth')
                    ->label('Worth')
                    ->state(fn (Event $record): int => $record->points
                        ?? app(ScoringService::class)->rules($record->season)[$record->type->value])
                    ->description(fn (Event $record): ?string => $record->points === null ? null : 'overridden'),
                TextColumn::make('note')->wrap()->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Season')
                    ->relationship('season', 'number')
                    ->preload(),
                SelectFilter::make('castaway_id')
                    ->label('Castaway')
                    ->relationship('castaway', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->options(collect(EventType::storable())
                        ->mapWithKeys(fn (EventType $type): array => [$type->value => $type->label()])
                        ->all())
                    ->multiple(),
                Filter::make('episode_number')
                    ->schema([
                        TextInput::make('episode_number')->label('Episode')->numeric(),
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        $data['episode_number'] ?? null,
                        fn ($query, $episode) => $query->where('episode_number', $episode),
                    )),
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
