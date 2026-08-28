<?php

use App\Data\EpisodeRecording;
use App\Data\EpisodeRecordingResult;
use App\Enums\EventType;
use App\Exceptions\RecordingException;
use App\Models\Castaway;
use App\Models\Event;
use App\Models\Manager;
use App\Models\Season;
use App\Services\EpisodeRecorder;
use App\Services\ScoringService;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->season = Season::factory()->active()->create(['number' => 51, 'roster_size' => 6]);
    app(ScoringService::class)->seedDefaultRules($this->season);

    Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Jyrki', 'draft_slot' => 1]);
    Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Rein', 'draft_slot' => 2]);

    foreach ([
        ['name' => 'Jelly Aquino', 'tribe' => 'Lagi'],
        ['name' => 'Kilby Reeves', 'tribe' => 'Lagi'],
        ['name' => 'Thien An Do', 'tribe' => 'Lagi'],
        ['name' => 'Rob Mariano', 'tribe' => 'Vula'],
        ['name' => 'Sara Novak', 'tribe' => 'Vula'],
        ['name' => 'Tom Petit', 'tribe' => 'Vula'],
    ] as $attributes) {
        Castaway::factory()->create([...$attributes, 'season_id' => $this->season->id]);
    }

    $this->recorder = app(EpisodeRecorder::class);
});

/**
 * @param  array<string, mixed>  $input
 */
function record(array $input): EpisodeRecordingResult
{
    return test()->recorder->record(test()->season, EpisodeRecording::fromArray($input));
}

function eventsFor(string $name): Collection
{
    $castaway = Castaway::query()->where('name', $name)->sole();

    return Event::query()->where('castaway_id', $castaway->id)->get();
}

describe('recording an episode', function () {
    it('creates the episode row and the events', function () {
        $result = record([
            'episode' => 1,
            'title' => 'The Open Era Begins',
            'air_date' => '2026-09-23',
            'voted_out' => ['Jelly'],
            'tribe_immunity' => ['Vula'],
        ]);

        expect($result->episode->number)->toBe(1)
            ->and($result->episode->title)->toBe('The Open Era Begins')
            ->and($result->warnings)->toBeEmpty()
            ->and(eventsFor('Jelly Aquino')->pluck('type')->all())->toBe([EventType::VotedOut]);
    });

    it('is idempotent: calling it twice corrects rather than duplicates', function () {
        record(['episode' => 1, 'voted_out' => ['Jelly'], 'idol_found' => ['Kilby']]);
        record(['episode' => 1, 'voted_out' => ['Kilby']]);

        expect(Event::query()->where('episode_number', 1)->count())->toBe(1)
            ->and(eventsFor('Jelly Aquino'))->toBeEmpty()
            ->and(eventsFor('Kilby Reeves')->pluck('type')->all())->toBe([EventType::VotedOut]);
    });

    it('leaves other episodes alone when one is re-recorded', function () {
        record(['episode' => 1, 'voted_out' => ['Jelly']]);
        record(['episode' => 2, 'voted_out' => ['Kilby']]);
        record(['episode' => 2, 'voted_out' => ['Sara']]);

        expect(Event::query()->where('episode_number', 1)->count())->toBe(1)
            ->and(eventsFor('Kilby Reeves'))->toBeEmpty()
            ->and(eventsFor('Sara Novak')->pluck('type')->all())->toBe([EventType::VotedOut]);
    });

    it('does not blank a title when a later correcting call omits it', function () {
        record(['episode' => 1, 'title' => 'Original Title', 'voted_out' => ['Jelly']]);
        $result = record(['episode' => 1, 'voted_out' => ['Kilby']]);

        expect($result->episode->title)->toBe('Original Title');
    });

    it('rejects an episode number below one', function () {
        record(['episode' => 0]);
    })->throws(RecordingException::class, 'Episode numbers start at 1.');
});

describe('tribe immunity', function () {
    it('awards every living member when the entry names a tribe', function () {
        record(['episode' => 1, 'tribe_immunity' => ['Lagi']]);

        expect(Event::query()->where('type', EventType::TribeImmunity)->count())->toBe(3)
            ->and(eventsFor('Jelly Aquino')->pluck('type')->all())->toBe([EventType::TribeImmunity]);
    });

    it('skips members who are already out', function () {
        record(['episode' => 1, 'voted_out' => ['Jelly']]);
        record(['episode' => 2, 'tribe_immunity' => ['Lagi']]);

        $awarded = Event::query()->where('episode_number', 2)->where('type', EventType::TribeImmunity)->count();

        expect($awarded)->toBe(2);
    });

    it('falls back to a castaway name when no tribe matches', function () {
        record(['episode' => 1, 'tribe_immunity' => ['Rob']]);

        expect(eventsFor('Rob Mariano')->pluck('type')->all())->toBe([EventType::TribeImmunity])
            ->and(Event::query()->where('type', EventType::TribeImmunity)->count())->toBe(1);
    });

    it('warns rather than failing when a known tribe has nobody left', function () {
        record(['episode' => 1, 'voted_out' => ['Jelly']]);
        record(['episode' => 2, 'voted_out' => ['Kilby']]);
        record(['episode' => 3, 'voted_out' => ['Thien An']]);
        $result = record(['episode' => 4, 'tribe_immunity' => ['Lagi']]);

        expect($result->warnings)->toContain('No living members of tribe "Lagi" to award tribe immunity to.');
    });
});

describe('the merge', function () {
    it('awards made_merge to everyone alive at the start of the episode', function () {
        record(['episode' => 1, 'voted_out' => ['Jelly']]);
        record(['episode' => 2, 'merge' => true, 'voted_out' => ['Kilby']]);

        // Jelly went out first and misses it; Kilby goes out this episode but
        // still started it, so they made the merge.
        expect(Event::query()->where('type', EventType::MadeMerge)->count())->toBe(5)
            ->and(eventsFor('Jelly Aquino')->pluck('type')->all())->toBe([EventType::VotedOut])
            ->and(eventsFor('Kilby Reeves')->pluck('type')->all())
            ->toBe([EventType::MadeMerge, EventType::VotedOut]);
    });

    it('does not award the merge twice when the episode is re-recorded', function () {
        record(['episode' => 2, 'merge' => true]);
        record(['episode' => 2, 'merge' => true]);

        expect(Event::query()->where('type', EventType::MadeMerge)->count())->toBe(6);
    });
});

describe('warnings', function () {
    it('records an award to someone already eliminated and flags it', function () {
        record(['episode' => 1, 'voted_out' => ['Jelly']]);
        $result = record(['episode' => 2, 'individual_immunity' => ['Jelly']]);

        expect(eventsFor('Jelly Aquino')->pluck('type')->all())
            ->toBe([EventType::VotedOut, EventType::IndividualImmunity])
            ->and($result->warnings[0])
            ->toContain('Jelly Aquino was already out before episode 2')
            ->and($result->warnings[0])->toContain('Check the episode number.');
    });

    it('skips an unknown name with a warning instead of failing the call', function () {
        $result = record(['episode' => 1, 'voted_out' => ['Probst'], 'idol_found' => ['Kilby']]);

        expect($result->warnings)->toContain('No castaway matches "Probst". Skipped.')
            ->and(eventsFor('Kilby Reeves')->pluck('type')->all())->toBe([EventType::IdolFound]);
    });

    it('names the candidates when an entry is ambiguous', function () {
        Castaway::factory()->create(['name' => 'Sara Reeves', 'season_id' => $this->season->id]);

        $result = record(['episode' => 1, 'voted_out' => ['Reeves']]);

        expect($result->warnings[0])->toContain('matches more than one castaway: Kilby Reeves, Sara Reeves');
    });
});

describe('the returned picture', function () {
    it('returns the events, the standings and who is still in', function () {
        $result = record(['episode' => 1, 'voted_out' => ['Jelly'], 'idol_found' => ['Rob']]);

        expect($result->events)->toHaveCount(2)
            ->and($result->scoreboard->episodesAired)->toBe(1)
            ->and(array_map(fn ($s): string => $s->castaway->name, $result->scoreboard->stillIn))
            ->not->toContain('Jelly Aquino')
            ->and($result->toArray()['still_in'])->toHaveCount(5);
    });
});

describe('final placements', function () {
    it('records the finale bonuses against the last episode', function () {
        record(['episode' => 1, 'voted_out' => ['Jelly']]);
        record(['episode' => 2]);

        $events = $this->recorder->setFinalPlacements($this->season, [
            'winner' => 'Rob',
            'runner_up' => 'Sara',
            'third' => 'Tom',
        ]);

        expect($events)->toHaveCount(3)
            ->and($events[0]->episode_number)->toBe(2)
            ->and(eventsFor('Rob Mariano')->pluck('type')->all())->toBe([EventType::Winner]);
    });

    it('replaces placements rather than stacking them', function () {
        record(['episode' => 1]);
        $this->recorder->setFinalPlacements($this->season, ['winner' => 'Rob']);
        $this->recorder->setFinalPlacements($this->season, ['winner' => 'Sara']);

        expect(Event::query()->where('type', EventType::Winner)->count())->toBe(1)
            ->and(eventsFor('Rob Mariano'))->toBeEmpty()
            ->and(eventsFor('Sara Novak')->pluck('type')->all())->toBe([EventType::Winner]);
    });

    it('refuses when no episodes have aired', function () {
        $this->recorder->setFinalPlacements($this->season, ['winner' => 'Rob']);
    })->throws(RecordingException::class, 'No episodes have been recorded yet');

    it('survives a re-recorded episode wiping the finale placements', function () {
        record(['episode' => 1]);
        $this->recorder->setFinalPlacements($this->season, ['winner' => 'Rob']);

        // Re-recording the finale clears its events, placements included.
        record(['episode' => 1, 'voted_out' => ['Jelly']]);

        expect(Event::query()->where('type', EventType::Winner)->count())->toBe(0);
    });
});
