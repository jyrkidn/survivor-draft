<?php

use App\Enums\EventType;
use App\Models\Castaway;
use App\Models\DraftPick;
use App\Models\Episode;
use App\Models\Event;
use App\Models\Manager;
use App\Models\ScoringRule;
use App\Models\Season;
use App\Services\ScoringService;

beforeEach(function () {
    $this->season = Season::factory()->active()->create(['number' => 51, 'roster_size' => 21]);
    app(ScoringService::class)->seedDefaultRules($this->season);
    $this->scoring = app(ScoringService::class);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function castaway(array $attributes = []): Castaway
{
    return Castaway::factory()->create([...$attributes, 'season_id' => test()->season->id]);
}

function airEpisodes(int $count): void
{
    foreach (range(1, $count) as $number) {
        Episode::factory()->create(['season_id' => test()->season->id, 'number' => $number]);
    }
}

function happened(Castaway $castaway, EventType $type, int $episode, ?int $points = null): Event
{
    return Event::factory()->create([
        'season_id' => test()->season->id,
        'castaway_id' => $castaway->id,
        'type' => $type,
        'episode_number' => $episode,
        'points' => $points,
    ]);
}

describe('survival points', function () {
    it('awards nothing to a castaway voted out in episode one', function () {
        airEpisodes(3);
        $jelly = castaway(['name' => 'Jelly Aquino']);
        happened($jelly, EventType::VotedOut, 1);

        $score = $this->scoring->scoreCastaway($jelly);

        expect($score->survivedEpisodes)->toBe(0)
            ->and($score->survivalPoints)->toBe(0)
            ->and($score->totalPoints)->toBe(0)
            ->and($score->status)->toBe('out')
            ->and($score->exitEpisode)->toBe(1);
    });

    it('awards one point per episode started but not eliminated in', function () {
        airEpisodes(9);
        $kilby = castaway(['name' => 'Kilby Reeves']);
        happened($kilby, EventType::VotedOut, 5);

        $score = $this->scoring->scoreCastaway($kilby);

        // Voted out in episode 5 means they started and survived 1 through 4.
        expect($score->survivedEpisodes)->toBe(4)
            ->and($score->survivalPoints)->toBe(4);
    });

    it('gives a castaway who is still in the full aired-episode count', function () {
        airEpisodes(7);
        $thienAn = castaway(['name' => 'Thien An Do']);

        $score = $this->scoring->scoreCastaway($thienAn);

        expect($score->survivedEpisodes)->toBe(7)
            ->and($score->survivalPoints)->toBe(7)
            ->and($score->status)->toBe('in')
            ->and($score->exitEpisode)->toBeNull();
    });

    it('gives a finalist the full count on top of the placement bonus', function () {
        airEpisodes(14);
        $rob = castaway(['name' => 'Rob Mariano']);
        happened($rob, EventType::MadeMerge, 7);
        happened($rob, EventType::Winner, 14);

        $score = $this->scoring->scoreCastaway($rob);

        expect($score->survivedEpisodes)->toBe(14)
            ->and($score->totalPoints)->toBe(14 + 5 + 25)
            ->and($score->placement)->toBe(EventType::Winner)
            ->and($score->status)->toBe('in');
    });

    it('scores a pre-merge quit', function () {
        airEpisodes(12);
        $quitter = castaway(['name' => 'Sam Bell']);
        happened($quitter, EventType::Quit, 3);

        $score = $this->scoring->scoreCastaway($quitter);

        expect($score->survivedEpisodes)->toBe(2)
            ->and($score->totalPoints)->toBe(2)
            ->and($score->status)->toBe('out');
    });

    it('never counts more survival than episodes actually aired', function () {
        airEpisodes(2);
        $ahead = castaway(['name' => 'Early Entry']);
        happened($ahead, EventType::VotedOut, 9);

        $score = $this->scoring->scoreCastaway($ahead);

        expect($score->survivedEpisodes)->toBe(2);
    });

    it('takes the earliest elimination when more than one is recorded', function () {
        airEpisodes(10);
        $castaway = castaway(['name' => 'Double Entry']);
        happened($castaway, EventType::LostFiremaking, 9);
        happened($castaway, EventType::VotedOut, 4);

        expect($this->scoring->scoreCastaway($castaway)->exitEpisode)->toBe(4);
    });
});

describe('event points', function () {
    it('sums each event at its season rule', function () {
        airEpisodes(6);
        $castaway = castaway();
        happened($castaway, EventType::IdolFound, 2);
        happened($castaway, EventType::IndividualImmunity, 4);
        happened($castaway, EventType::MadeMerge, 5);

        $score = $this->scoring->scoreCastaway($castaway);

        expect($score->eventPoints)->toBe(3 + 2 + 5)
            ->and($score->totalPoints)->toBe(6 + 10);
    });

    it('lets a single event override the rule', function () {
        airEpisodes(1);
        $castaway = castaway();
        happened($castaway, EventType::IdolFound, 1, points: 99);

        expect($this->scoring->scoreCastaway($castaway)->eventPoints)->toBe(99);
    });

    it('records what each event was worth in the breakdown', function () {
        airEpisodes(2);
        $castaway = castaway();
        $event = happened($castaway, EventType::TribeImmunity, 1);

        $breakdown = $this->scoring->scoreCastaway($castaway)->breakdown;

        expect($breakdown)->toHaveCount(1)
            ->and($breakdown[0]['event_id'])->toBe($event->id)
            ->and($breakdown[0]['points'])->toBe(1)
            ->and($breakdown[0]['episode'])->toBe(1);
    });
});

describe('re-scoring', function () {
    it('re-scores the whole season when a rule changes', function () {
        airEpisodes(4);
        $castaway = castaway();
        happened($castaway, EventType::IdolFound, 2);

        expect($this->scoring->scoreCastaway($castaway)->totalPoints)->toBe(4 + 3);

        ScoringRule::query()
            ->where('season_id', $this->season->id)
            ->where('type', EventType::IdolFound)
            ->update(['points' => 10]);

        expect($this->scoring->scoreCastaway($castaway)->totalPoints)->toBe(4 + 10);
    });

    it('re-scores retroactively when the survival rule changes', function () {
        airEpisodes(5);
        $castaway = castaway();

        ScoringRule::query()
            ->where('season_id', $this->season->id)
            ->where('type', EventType::Survived)
            ->update(['points' => 2]);

        expect($this->scoring->scoreCastaway($castaway)->survivalPoints)->toBe(10);
    });

    it('undoes a mistake entered five episodes ago by deleting one event', function () {
        airEpisodes(9);
        $castaway = castaway();
        $mistake = happened($castaway, EventType::IdolFound, 4);

        expect($this->scoring->scoreCastaway($castaway)->totalPoints)->toBe(9 + 3);

        $mistake->delete();

        expect($this->scoring->scoreCastaway($castaway)->totalPoints)->toBe(9);
    });

    it('brings a castaway back to life when the elimination event is deleted', function () {
        airEpisodes(6);
        $castaway = castaway();
        $wrong = happened($castaway, EventType::VotedOut, 2);

        expect($this->scoring->scoreCastaway($castaway)->status)->toBe('out');

        $wrong->delete();

        $score = $this->scoring->scoreCastaway($castaway);
        expect($score->status)->toBe('in')->and($score->survivedEpisodes)->toBe(6);
    });

    it('falls back to the built-in default when a season has no rule row', function () {
        airEpisodes(2);
        ScoringRule::query()->where('season_id', $this->season->id)->delete();
        $castaway = castaway();
        happened($castaway, EventType::Winner, 2);

        expect($this->scoring->scoreCastaway($castaway)->totalPoints)->toBe(2 + 25);
    });
});

describe('scoreboard', function () {
    beforeEach(function () {
        $this->one = Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Jyrki', 'draft_slot' => 1]);
        $this->two = Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Rein', 'draft_slot' => 2]);
    });

    it('totals each manager over their drafted roster', function () {
        airEpisodes(5);
        $mine = castaway(['name' => 'Mine One']);
        $theirs = castaway(['name' => 'Theirs One']);
        draft($this->one, $mine, 1);
        draft($this->two, $theirs, 2);
        happened($mine, EventType::IdolFound, 3);

        $board = $this->scoring->scoreboard($this->season);

        expect($board->standings[0]->manager->name)->toBe('Jyrki')
            ->and($board->standings[0]->totalPoints)->toBe(5 + 3)
            ->and($board->standings[1]->totalPoints)->toBe(5);
    });

    it('scores undrafted castaways so the board can show what was left on the table', function () {
        airEpisodes(4);
        $nobodys = castaway(['name' => 'Undrafted Ursula']);
        happened($nobodys, EventType::IdolFound, 1);

        $board = $this->scoring->scoreboard($this->season);

        expect($board->undrafted)->toHaveCount(1)
            ->and($board->undrafted[0]->totalPoints)->toBe(4 + 3)
            ->and($board->undrafted[0]->owner)->toBeNull();
    });

    it('breaks a points tie on the highest-placing castaway', function () {
        airEpisodes(10);
        $winner = castaway(['name' => 'Won It']);
        $alsoRan = castaway(['name' => 'Came Third']);
        draft($this->one, $winner, 1);
        draft($this->two, $alsoRan, 2);

        // Both managers land on the same total, from different events.
        happened($winner, EventType::Winner, 10);
        happened($alsoRan, EventType::Third, 10);
        happened($alsoRan, EventType::IdolFound, 3, points: 15);

        $board = $this->scoring->scoreboard($this->season);

        expect($board->standings[0]->totalPoints)->toBe($board->standings[1]->totalPoints)
            ->and($board->standings[0]->manager->name)->toBe('Jyrki')
            ->and($board->leadSummary())->toContain('tie-break');
    });

    it('lists who is still in', function () {
        airEpisodes(3);
        $out = castaway(['name' => 'Gone Already']);
        castaway(['name' => 'Still Here']);
        happened($out, EventType::VotedOut, 2);

        $board = $this->scoring->scoreboard($this->season);

        expect($board->stillIn)->toHaveCount(1)
            ->and($board->stillIn[0]->castaway->name)->toBe('Still Here');
    });
});

describe('living at the start of an episode', function () {
    it('excludes anyone eliminated in an earlier episode but keeps this episode intact', function () {
        airEpisodes(5);
        $early = castaway(['name' => 'Out In Two']);
        $thisWeek = castaway(['name' => 'Out In Four']);
        castaway(['name' => 'Still Going']);
        happened($early, EventType::VotedOut, 2);
        happened($thisWeek, EventType::VotedOut, 4);

        $living = $this->scoring->livingAtStartOfEpisode($this->season, 4);

        expect($living->pluck('name')->all())->toBe(['Out In Four', 'Still Going']);
    });
});

function draft(Manager $manager, Castaway $castaway, int $pickNumber): DraftPick
{
    return DraftPick::factory()->create([
        'season_id' => test()->season->id,
        'manager_id' => $manager->id,
        'castaway_id' => $castaway->id,
        'pick_number' => $pickNumber,
        'round' => (int) ceil($pickNumber / 2),
    ]);
}
