<?php

namespace App\Filament\Pages;

use App\Data\EpisodeRecording;
use App\Data\Scoreboard;
use App\Enums\EventType;
use App\Exceptions\LeagueException;
use App\Models\Castaway;
use App\Models\Season;
use App\Services\EpisodeRecorder;
use App\Services\ScoringService;
use App\Services\SeasonService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The same call `record_episode` makes, with a form in front of it.
 *
 * Nothing here reimplements the recording rules — it collects names and hands
 * them to EpisodeRecorder, so the panel and the MCP tool cannot drift.
 */
class RecordEpisode extends Page
{
    protected string $view = 'filament.pages.record-episode';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static ?string $navigationLabel = 'Record episode';

    protected static ?int $navigationSort = -1;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $season = $this->season();

        $this->getSchema('form')?->fill([
            'episode' => $season === null ? 1 : (int) $season->episodes()->max('number') + 1,
            'merge' => false,
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('record-episode')
                ->livewireSubmitHandler('record')
                ->footer([
                    Actions::make([
                        Action::make('record')
                            ->label('Record episode')
                            ->submit('record'),
                    ]),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Episode')
                    ->description('Saving replaces everything already recorded for this episode number, so '
                        .'correcting a mistake means submitting the whole episode again.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('episode')
                            ->label('Episode number')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->live(),
                        TextInput::make('title'),
                        DatePicker::make('air_date'),
                        Toggle::make('merge')
                            ->label('The merge happened this episode')
                            ->helperText('Awards the merge bonus to everyone who started the episode.'),
                        Textarea::make('notes')->rows(2)->columnSpan(2),
                    ]),

                Section::make('Out of the game')
                    ->columns(2)
                    ->schema([
                        $this->castawayPicker(EventType::VotedOut),
                        $this->castawayPicker(EventType::LostFiremaking),
                        $this->castawayPicker(EventType::Quit),
                        $this->castawayPicker(EventType::Medevac),
                    ]),

                Section::make('Wins and finds')
                    ->columns(2)
                    ->schema([
                        $this->castawayPicker(EventType::IndividualImmunity),
                        Select::make(EventType::TribeImmunity->value)
                            ->label('Won tribe immunity')
                            ->multiple()
                            ->options(fn (): array => $this->tribeOptions())
                            ->helperText('Awards every living member of the tribe.'),
                        $this->castawayPicker(EventType::IdolFound),
                        $this->castawayPicker(EventType::IdolPlayed),
                        $this->castawayPicker(EventType::AdvantageFound),
                        $this->castawayPicker(EventType::FireWin),
                    ]),
            ])
            ->statePath('data');
    }

    public function record(): void
    {
        $season = $this->season();

        if ($season === null) {
            Notification::make()
                ->title('No active season')
                ->body('Create a season and mark it active before recording episodes.')
                ->danger()
                ->send();

            return;
        }

        /** @var array<string, mixed> $state */
        $state = $this->getSchema('form')?->getState() ?? [];

        try {
            $result = app(EpisodeRecorder::class)->record(
                $season,
                EpisodeRecording::fromArray($this->toRecordingInput($state)),
            );
        } catch (LeagueException $exception) {
            Notification::make()->title('Could not record')->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title("Episode {$result->episode->number} recorded")
            ->body(sprintf(
                '%d event%s. %s',
                count($result->events),
                count($result->events) === 1 ? '' : 's',
                $result->scoreboard->leadSummary(),
            ))
            ->success()
            ->send();

        // Warnings are advisory, never failures — usually a mis-numbered episode.
        foreach ($result->warnings as $warning) {
            Notification::make()->title('Check this')->body($warning)->warning()->persistent()->send();
        }
    }

    /**
     * The castaways still in the game when this episode starts, which is what
     * the pickers offer.
     *
     * @return array<string, string>
     */
    public function livingOptions(): array
    {
        $season = $this->season();

        if ($season === null) {
            return [];
        }

        $episode = (int) ($this->data['episode'] ?? 1);

        return app(ScoringService::class)
            ->livingAtStartOfEpisode($season, max(1, $episode))
            ->mapWithKeys(fn (Castaway $castaway): array => [$castaway->name => $castaway->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function tribeOptions(): array
    {
        $season = $this->season();

        if ($season === null) {
            return [];
        }

        return $season->castaways()
            ->whereNotNull('tribe')
            ->orderBy('tribe')
            ->pluck('tribe', 'tribe')
            ->all();
    }

    /**
     * The standings as they read right now, shown alongside the form.
     */
    public function currentScoreboard(): ?Scoreboard
    {
        $season = $this->season();

        return $season === null ? null : app(ScoringService::class)->scoreboard($season);
    }

    public function season(): ?Season
    {
        try {
            return app(SeasonService::class)->resolve();
        } catch (LeagueException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function toRecordingInput(array $state): array
    {
        $input = [
            'episode' => (int) ($state['episode'] ?? 1),
            'merge' => (bool) ($state['merge'] ?? false),
        ];

        foreach (['title', 'air_date', 'notes'] as $key) {
            if (! empty($state[$key])) {
                $input[$key] = $state[$key];
            }
        }

        foreach (EventType::recordableInBulk() as $type) {
            $names = $state[$type->value] ?? [];

            if (is_array($names) && $names !== []) {
                $input[$type->value] = $names;
            }
        }

        return $input;
    }

    private function castawayPicker(EventType $type): Select
    {
        return Select::make($type->value)
            ->label($type->label())
            ->multiple()
            ->searchable()
            ->options(fn (): array => $this->livingOptions());
    }
}
