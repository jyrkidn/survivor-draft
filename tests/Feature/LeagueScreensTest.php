<?php

use App\Data\EpisodeRecording;
use App\Models\Castaway;
use App\Models\Manager;
use App\Models\Season;
use App\Models\User;
use App\Services\DraftService;
use App\Services\EpisodeRecorder;
use App\Services\ScoringService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->season = Season::factory()->active()->create([
        'number' => 51,
        'title' => 'The Open Era',
        'premiere_date' => '2026-09-23',
        'roster_size' => 6,
    ]);
    app(ScoringService::class)->seedDefaultRules($this->season);

    $this->sarah = Manager::factory()->create([
        'season_id' => $this->season->id, 'name' => 'Sarah', 'draft_slot' => 1,
    ]);
    $this->tom = Manager::factory()->create([
        'season_id' => $this->season->id, 'name' => 'Tom', 'draft_slot' => 2,
    ]);

    foreach ([
        ['name' => 'Layla Amari', 'tribe' => 'Yasi'],
        ['name' => 'Marisol Vega', 'tribe' => 'Lomani'],
        ['name' => 'Gus Halvorsen', 'tribe' => 'Yasi'],
        ['name' => 'Beatriz Cruz', 'tribe' => 'Lomani'],
        ['name' => 'Rory Mackey', 'tribe' => 'Namosi'],
        ['name' => 'Simone Boyer', 'tribe' => 'Namosi'],
    ] as $attributes) {
        Castaway::factory()->create([...$attributes, 'season_id' => $this->season->id]);
    }

    $this->sarahUser = User::factory()->create(['manager_id' => $this->sarah->id]);
    $this->tomUser = User::factory()->create(['manager_id' => $this->tom->id]);
});

function draftPick(string $manager, string $castaway): void
{
    app(DraftService::class)->makePick(test()->season, $manager, $castaway);
}

function airEpisode(int $number, array $input = []): void
{
    app(EpisodeRecorder::class)->record(
        test()->season,
        EpisodeRecording::fromArray([...$input, 'episode' => $number]),
    );
}

describe('access', function () {
    it('sends guests to the Filament login, the only one there is', function () {
        $this->get('/scoreboard')->assertRedirect('/admin/login');
        $this->get('/cast')->assertRedirect('/admin/login');
        $this->get('/draft')->assertRedirect('/admin/login');
    });

    it('lands the root on the scoreboard', function () {
        $this->actingAs($this->sarahUser)->get('/')->assertRedirect('/scoreboard');
    });

    it('serves a dark-only shell with the league ground painted before the CSS lands', function () {
        $html = $this->actingAs($this->sarahUser)->get('/scoreboard')->assertOk()->getContent();

        expect($html)
            ->toContain('<html lang="en" class="dark">')
            ->toContain('background-color: #06161f')
            ->toContain('class="bg-ink-deep font-body text-sand antialiased"')
            // No appearance switcher: the design is dark only.
            ->not->toContain('prefers-color-scheme');
    });
});

describe('scoreboard', function () {
    beforeEach(function () {
        draftPick('Sarah', 'Layla');
        draftPick('Tom', 'Marisol');
        draftPick('Sarah', 'Beatriz');
        draftPick('Tom', 'Gus');
        $this->actingAs($this->sarahUser);
    });

    it('leads with the gap and names the leader', function () {
        airEpisode(1, ['idol_found' => ['Layla']]);

        $this->get('/scoreboard')->assertInertia(fn (Assert $page) => $page
            ->component('Scoreboard')
            ->where('episodesAired', 1)
            ->where('verdict.level', false)
            // Sarah: Layla 1+3, Beatriz 1. Tom: two castaways on 1 each.
            ->where('verdict.gap', 3)
            ->where('verdict.leader_name', 'Sarah')
            ->where('verdict.leader_slot', 1)
        );
    });

    it('reads LEVEL rather than a signed zero when the two are tied', function () {
        airEpisode(1);

        $this->get('/scoreboard')->assertInertia(fn (Assert $page) => $page
            ->where('verdict.level', true)
            ->where('verdict.gap', 0)
            ->where('verdict.leader_name', null)
        );
    });

    it('scales the total bars to the leader', function () {
        airEpisode(1, ['idol_found' => ['Layla']]);

        $this->get('/scoreboard')->assertInertia(fn (Assert $page) => $page
            ->where('totals.0.name', 'Sarah')
            ->where('totals.0.points', 5)
            ->where('totals.0.bar_percent', 100)
            ->where('totals.1.name', 'Tom')
            ->where('totals.1.points', 2)
            ->where('totals.1.bar_percent', 40)
        );
    });

    it('builds the gap history one entry per aired episode', function () {
        airEpisode(1, ['idol_found' => ['Layla']]);
        airEpisode(2, ['idol_found' => ['Marisol'], 'individual_immunity' => ['Marisol']]);

        $this->get('/scoreboard')->assertInertia(fn (Assert $page) => $page
            ->has('gapByEpisode', 2)
            ->where('gapByEpisode.0.episode', 1)
            ->where('gapByEpisode.0.gap', 3)
            ->where('gapByEpisode.0.leader_slot', 1)
            // Marisol's idol and immunity swing it to Tom by episode 2.
            ->where('gapByEpisode.1.gap', -2)
            ->where('gapByEpisode.1.leader_slot', 2)
        );
    });

    it('shows nothing but zeros before the premiere', function () {
        $this->get('/scoreboard')->assertInertia(fn (Assert $page) => $page
            ->where('seasonStarted', false)
            ->where('episodesAired', 0)
            ->has('gapByEpisode', 0)
            ->where('totals.0.points', 0)
        );
    });

    it('lists each roster with a live count and an exit episode', function () {
        airEpisode(1, ['voted_out' => ['Beatriz']]);

        $this->get('/scoreboard')->assertInertia(fn (Assert $page) => $page
            ->where('rosters.0.name', 'Sarah')
            ->where('rosters.0.alive', 1)
            ->where('rosters.0.castaways.1.name', 'Beatriz Cruz')
            ->where('rosters.0.castaways.1.status', 'out')
            ->where('rosters.0.castaways.1.exit_episode', 1)
        );
    });
});

describe('cast', function () {
    beforeEach(fn () => $this->actingAs($this->sarahUser));

    it('sorts by tribe then draft position', function () {
        draftPick('Sarah', 'Beatriz');
        draftPick('Tom', 'Marisol');

        $this->get('/cast')->assertInertia(fn (Assert $page) => $page
            ->component('Cast')
            ->where('tribes', ['Lomani', 'Namosi', 'Yasi'])
            // Beatriz went at pick 1 so leads Lomani, ahead of Marisol at pick 2.
            ->where('castaways.0.name', 'Beatriz Cruz')
            ->where('castaways.0.owner_name', 'Sarah')
            ->where('castaways.0.owner_initial', 'S')
            ->where('castaways.1.name', 'Marisol Vega')
            ->where('castaways.1.owner_name', 'Tom')
            ->where('castaways.1.owner_initial', 'T')
        );
    });

    it('lengthens the badge initials until they tell the managers apart', function () {
        $this->sarah->update(['name' => 'Sam']);
        $this->tom->update(['name' => 'Sara']);
        draftPick('Sam', 'Beatriz');
        draftPick('Sara', 'Marisol');

        $this->get('/cast')->assertInertia(fn (Assert $page) => $page
            ->where('castaways.0.owner_initial', 'SAM')
            ->where('castaways.1.owner_initial', 'SAR')
        );
    });

    it('marks undrafted castaways as owned by nobody', function () {
        $this->get('/cast')->assertInertia(fn (Assert $page) => $page
            ->where('castaways.0.owner_name', null)
            ->where('castaways.0.owner_initial', null)
            ->where('castaways.0.owner_slot', null)
        );
    });

    it('counts the living and the dead', function () {
        airEpisode(1, ['voted_out' => ['Layla']]);

        $this->get('/cast')->assertInertia(fn (Assert $page) => $page
            ->where('aliveCount', 5)
            ->where('outCount', 1)
            ->where('castaways.5.name', 'Layla Amari')
            ->where('castaways.5.status', 'out')
            ->where('castaways.5.exit_episode', 1)
        );
    });
});

describe('draft', function () {
    it('starts in the pre-draft state with nothing picked', function () {
        $this->actingAs($this->sarahUser)->get('/draft')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Draft')
                ->has('picks', 0)
                ->where('totalPicks', 6)
                ->where('rounds', 3)
                ->where('nextPickNumber', 1)
                ->where('onTheClock.name', 'Sarah')
                ->where('castLocked', 6)
                ->has('available', 6)
            );
    });

    it('records a pick and hands the clock over', function () {
        $layla = Castaway::query()->where('name', 'Layla Amari')->sole();

        $this->actingAs($this->sarahUser)
            ->post('/draft/pick', ['castaway_id' => $layla->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Sarah takes Layla Amari.');

        $this->actingAs($this->sarahUser)->get('/draft')
            ->assertInertia(fn (Assert $page) => $page
                ->has('picks', 1)
                ->where('picks.0.pick_number', 1)
                ->where('picks.0.manager_slot', 1)
                ->where('picks.0.castaway.name', 'Layla Amari')
                ->where('onTheClock.name', 'Tom')
                ->has('available', 5)
                ->where('managers.0.picks_made', 1)
            );
    });

    it('assigns the pick to whoever is on the clock, not to whoever is logged in', function () {
        // Both managers draft from one laptop, so Sarah's login making the
        // second pick still records it as Tom's.
        $layla = Castaway::query()->where('name', 'Layla Amari')->sole();
        $marisol = Castaway::query()->where('name', 'Marisol Vega')->sole();

        $this->actingAs($this->sarahUser)->post('/draft/pick', ['castaway_id' => $layla->id]);
        $this->actingAs($this->sarahUser)
            ->post('/draft/pick', ['castaway_id' => $marisol->id])
            ->assertSessionHas('success', 'Tom takes Marisol Vega.');

        expect($this->season->draftPicks()->where('pick_number', 2)->sole()->manager_id)
            ->toBe($this->tom->id);
    });

    it('lets a login with no manager of its own drive the draft', function () {
        $layla = Castaway::query()->where('name', 'Layla Amari')->sole();

        $this->actingAs(User::factory()->create())
            ->post('/draft/pick', ['castaway_id' => $layla->id])
            ->assertSessionHas('success', 'Sarah takes Layla Amari.');

        expect($this->season->draftPicks()->count())->toBe(1);
    });

    it('refuses a castaway from another season', function () {
        $stranger = Castaway::factory()->create(['season_id' => Season::factory()->create()->id]);

        $this->actingAs($this->sarahUser)
            ->post('/draft/pick', ['castaway_id' => $stranger->id])
            ->assertSessionHas('error', 'That castaway is not in this season.');
    });

    it('refuses a pick once the draft is complete', function () {
        foreach (app(DraftService::class)->pickOrder($this->season) as $slot) {
            draftPick($slot['manager']->name, app(DraftService::class)
                ->availableCastaways($this->season)->first()->name);
        }

        $spare = Castaway::factory()->create(['season_id' => $this->season->id]);

        $this->actingAs($this->sarahUser)
            ->post('/draft/pick', ['castaway_id' => $spare->id])
            ->assertSessionHas('error', 'The draft is already complete.');
    });

    it('undoes the last pick for either manager', function () {
        draftPick('Sarah', 'Layla');

        $this->actingAs($this->tomUser)->post('/draft/undo')->assertRedirect();

        expect($this->season->draftPicks()->count())->toBe(0);
    });

    it('locks the screen once the first episode airs', function () {
        draftPick('Sarah', 'Layla');
        airEpisode(1);

        $this->actingAs($this->sarahUser)->get('/draft')
            ->assertInertia(fn (Assert $page) => $page
                ->where('isLocked', true)
                ->where('episodesAired', 1)
            );
    });

    it('refuses a pick through the screen once the season has started', function () {
        airEpisode(1);
        $layla = Castaway::query()->where('name', 'Layla Amari')->sole();

        $this->actingAs($this->sarahUser)
            ->post('/draft/pick', ['castaway_id' => $layla->id])
            ->assertSessionHas('error', fn (string $error): bool => str_contains(
                $error,
                'The season has started, so the draft is locked',
            ));

        expect($this->season->draftPicks()->count())->toBe(0);
    });

    it('refuses an undo through the screen once the season has started', function () {
        draftPick('Sarah', 'Layla');
        airEpisode(1);

        $this->actingAs($this->sarahUser)
            ->post('/draft/undo')
            ->assertSessionHas('error', fn (string $error): bool => str_contains(
                $error,
                'the draft is locked',
            ));

        expect($this->season->draftPicks()->count())->toBe(1);
    });

    it('reports a completed draft with nobody on the clock', function () {
        foreach (app(DraftService::class)->pickOrder($this->season) as $slot) {
            draftPick($slot['manager']->name, app(DraftService::class)
                ->availableCastaways($this->season)->first()->name);
        }

        $this->actingAs($this->sarahUser)->get('/draft')
            ->assertInertia(fn (Assert $page) => $page
                ->where('isComplete', true)
                ->where('onTheClock', null)
                ->where('nextPickNumber', null)
                // Six castaways, two managers, three rounds — nobody is left over here.
                ->has('available', 0)
            );
    });
});
