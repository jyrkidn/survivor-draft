<?php

use App\Enums\EventType;
use App\Filament\Pages\RecordEpisode;
use App\Filament\Resources\Castaways\CastawayResource;
use App\Filament\Resources\Castaways\Pages\CreateCastaway;
use App\Filament\Resources\Castaways\Pages\ListCastaways;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Seasons\SeasonResource;
use App\Models\Castaway;
use App\Models\Event;
use App\Models\Manager;
use App\Models\Season;
use App\Models\User;
use App\Services\ScoringService;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->season = Season::factory()->active()->create(['number' => 51, 'roster_size' => 6]);
    app(ScoringService::class)->seedDefaultRules($this->season);

    Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Jyrki', 'draft_slot' => 1]);
    Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Rein', 'draft_slot' => 2]);

    foreach (['Jelly Aquino', 'Kilby Reeves', 'Rob Mariano'] as $name) {
        Castaway::factory()->create(['season_id' => $this->season->id, 'name' => $name, 'tribe' => 'Lagi']);
    }

    $this->actingAs(User::factory()->create());
});

describe('authentication', function () {
    it('is the only login screen', function () {
        auth()->logout();

        $this->get('/admin/login')->assertOk();
        $this->get('/login')->assertNotFound();
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    });

    it('keeps the panel behind a login', function () {
        auth()->logout();

        $this->get('/admin')->assertRedirect('/admin/login');
    });
});

describe('resources', function () {
    it('lists seasons, castaways and events', function () {
        $this->get(SeasonResource::getUrl('index'))->assertOk();
        $this->get(CastawayResource::getUrl('index'))->assertOk();
        $this->get(EventResource::getUrl('index'))->assertOk();
    });

    it('shows derived points and status on the castaway table without offering a total to edit', function () {
        $jelly = Castaway::query()->where('name', 'Jelly Aquino')->sole();
        $this->season->episodes()->create(['number' => 1]);
        Event::factory()->create([
            'season_id' => $this->season->id,
            'castaway_id' => $jelly->id,
            'type' => EventType::IdolFound,
            'episode_number' => 1,
        ]);

        livewire(ListCastaways::class)
            ->assertCanSeeTableRecords(Castaway::all())
            ->assertTableColumnStateSet('points', 1 + 3, $jelly)
            ->assertTableColumnStateSet('status', 'in', $jelly);

        // There is nowhere to store a total, so the form must not offer one.
        livewire(CreateCastaway::class)
            ->assertFormFieldDoesNotExist('points')
            ->assertFormFieldDoesNotExist('total_points')
            ->assertFormFieldDoesNotExist('status');
    });

    it('offers every event type except the derived one, and no points total', function () {
        livewire(CreateEvent::class)
            ->assertFormFieldExists('type', checkFieldUsing: function ($field): bool {
                $options = array_keys($field->getOptions());

                return count($options) === 15 && ! in_array('survived', $options, true);
            })
            // A per-event override is fine; a stored total is not.
            ->assertFormFieldExists('points')
            ->assertFormFieldDoesNotExist('total_points');
    });

    it('deletes an event from the flat log and re-scores', function () {
        $jelly = Castaway::query()->where('name', 'Jelly Aquino')->sole();
        $this->season->episodes()->create(['number' => 1]);
        $event = Event::factory()->create([
            'season_id' => $this->season->id,
            'castaway_id' => $jelly->id,
            'type' => EventType::IdolFound,
            'episode_number' => 1,
        ]);

        expect(app(ScoringService::class)->scoreCastaway($jelly)->totalPoints)->toBe(4);

        livewire(ListEvents::class)
            ->callAction(TestAction::make('delete')->table($event));

        expect(Event::query()->count())->toBe(0)
            ->and(app(ScoringService::class)->scoreCastaway($jelly->fresh())->totalPoints)->toBe(1);
    });
});

describe('the record episode page', function () {
    it('renders', function () {
        $this->get(RecordEpisode::getUrl())->assertOk();
    });

    it('defaults to the next episode number', function () {
        $this->season->episodes()->create(['number' => 3]);

        livewire(RecordEpisode::class)->assertSchemaStateSet(['episode' => 4], 'form');
    });

    it('records an episode through the same service the MCP tool uses', function () {
        livewire(RecordEpisode::class)
            ->fillForm([
                'episode' => 1,
                'title' => 'The Open Era Begins',
                'voted_out' => ['Jelly Aquino'],
                'idol_found' => ['Rob Mariano'],
            ], 'form')
            ->call('record')
            ->assertHasNoFormErrors();

        expect($this->season->episodes()->count())->toBe(1)
            ->and(Event::query()->count())->toBe(2)
            ->and(app(ScoringService::class)->scoreCastaway(
                Castaway::query()->where('name', 'Jelly Aquino')->sole()
            )->status)->toBe('out');
    });

    it('only offers castaways who are still in', function () {
        livewire(RecordEpisode::class)
            ->fillForm(['episode' => 1, 'voted_out' => ['Jelly Aquino']], 'form')
            ->call('record');

        $options = livewire(RecordEpisode::class)
            ->fillForm(['episode' => 2], 'form')
            ->instance()
            ->livingOptions();

        expect($options)->toHaveCount(2)->not->toHaveKey('Jelly Aquino');
    });

    it('replaces the episode rather than duplicating it when submitted twice', function () {
        foreach ([['Jelly Aquino'], ['Kilby Reeves']] as $votedOut) {
            livewire(RecordEpisode::class)
                ->fillForm(['episode' => 1, 'voted_out' => $votedOut], 'form')
                ->call('record');
        }

        expect(Event::query()->count())->toBe(1)
            ->and(Event::query()->sole()->castaway->name)->toBe('Kilby Reeves');
    });
});
