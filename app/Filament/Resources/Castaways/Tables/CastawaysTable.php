<?php

namespace App\Filament\Resources\Castaways\Tables;

use App\Data\CastawayScore;
use App\Models\Castaway;
use App\Services\PhotoIngestor;
use App\Services\ScoringService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CastawaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->circular()
                    ->state(fn (Castaway $record): ?string => $record->photoUrl()),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('nickname')->searchable()->toggleable(),
                TextColumn::make('age')->numeric()->sortable()->toggleable(),
                TextColumn::make('occupation')->searchable()->toggleable(),
                TextColumn::make('hometown')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tribe')->badge()->searchable()->sortable(),
                TextColumn::make('owner')
                    ->label('Drafted by')
                    ->state(fn (Castaway $record): string => $record->draftPick?->manager->name ?? '—'),
                // Derived, never stored. There is nowhere to type a total.
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Castaway $record): string => self::score($record)->status)
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'gray'),
                TextColumn::make('points')
                    ->label('Points')
                    ->state(fn (Castaway $record): int => self::score($record)->totalPoints)
                    ->sortable(false),
            ])
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Season')
                    ->relationship('season', 'number')
                    ->preload(),
                SelectFilter::make('tribe')
                    ->options(fn (): array => Castaway::query()
                        ->whereNotNull('tribe')
                        ->distinct()
                        ->pluck('tribe', 'tribe')
                        ->all()),
                TernaryFilter::make('drafted')
                    ->label('Drafted')
                    ->queries(
                        true: fn ($query) => $query->whereHas('draftPick'),
                        false: fn ($query) => $query->whereDoesntHave('draftPick'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('fetchPhoto')
                    ->label('Fetch photo')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        TextInput::make('url')
                            ->label('Image URL')
                            ->required()
                            ->url()
                            ->helperText('Must be https. Private and link-local addresses are refused.'),
                    ])
                    ->action(function (Castaway $record, array $data): void {
                        // Same PhotoIngestor the MCP tool calls, so both surfaces
                        // apply the same SSRF guard and the same fallback.
                        $result = app(PhotoIngestor::class)->ingest($record, $data['url']);

                        if ($result['stored']) {
                            Notification::make()
                                ->title("Stored {$record->name}'s photo")
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Photo not downloaded')
                            ->body($result['warning'])
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Points and status are derived on read. This is the repair shop, not a hot
     * path — a season is 21 castaways, so per-row derivation is fine.
     */
    private static function score(Castaway $record): CastawayScore
    {
        return app(ScoringService::class)->scoreCastaway($record);
    }
}
