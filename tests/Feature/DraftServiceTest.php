<?php

use App\Exceptions\DraftException;
use App\Models\Castaway;
use App\Models\Episode;
use App\Models\Manager;
use App\Models\Season;
use App\Services\DraftService;

beforeEach(function () {
    $this->season = Season::factory()->active()->create(['number' => 51, 'roster_size' => 21]);
    $this->jyrki = Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Jyrki', 'draft_slot' => 1]);
    $this->rein = Manager::factory()->create(['season_id' => $this->season->id, 'name' => 'Rein', 'draft_slot' => 2]);

    foreach (range(1, 21) as $index) {
        Castaway::factory()->create(['season_id' => $this->season->id, 'name' => "Castaway {$index}"]);
    }

    $this->draft = app(DraftService::class);
});

describe('the pick order', function () {
    it('runs twenty picks over ten rounds for twenty-one castaways and two managers', function () {
        expect($this->season->draftRounds())->toBe(10)
            ->and($this->season->totalDraftPicks())->toBe(20)
            ->and($this->draft->pickOrder($this->season))->toHaveCount(20);
    });

    it('gives slot one the odd picks and slot two the even ones', function () {
        $order = $this->draft->pickOrder($this->season);
        $slots = array_map(fn (array $pick): int => $pick['manager']->draft_slot, $order);

        // Straight alternating: no manager ever picks twice in a row.
        expect($slots)->toBe([1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2]);
    });

    it('numbers the rounds so each round holds one pick per manager', function () {
        $order = $this->draft->pickOrder($this->season);

        expect($order[0]['round'])->toBe(1)
            ->and($order[1]['round'])->toBe(1)
            ->and($order[2]['round'])->toBe(2)
            ->and($order[19]['round'])->toBe(10);
    });

    it('puts slot one on the clock before anything has happened', function () {
        expect($this->draft->onTheClock($this->season)->name)->toBe('Jyrki')
            ->and($this->draft->nextPickNumber($this->season))->toBe(1);
    });
});

describe('making a pick', function () {
    it('records the pick and advances the clock', function () {
        $pick = $this->draft->makePick($this->season, 'Jyrki', 'Castaway 1');

        expect($pick->pick_number)->toBe(1)
            ->and($pick->round)->toBe(1)
            ->and($pick->manager_id)->toBe($this->jyrki->id)
            ->and($this->draft->onTheClock($this->season)->name)->toBe('Rein');
    });

    it('refuses a pick from the manager who is not on the clock', function () {
        expect(fn () => $this->draft->makePick($this->season, 'Rein', 'Castaway 1'))
            ->toThrow(DraftException::class, "It is Jyrki's pick, not Rein's.");
    });

    it('refuses a castaway who is already taken, naming who took them', function () {
        $this->draft->makePick($this->season, 'Jyrki', 'Castaway 1');

        expect(fn () => $this->draft->makePick($this->season, 'Rein', 'Castaway 1'))
            ->toThrow(DraftException::class, 'Castaway 1 was already taken by Jyrki at pick 1.');
    });

    it('resolves castaway names loosely', function () {
        $kilby = Castaway::factory()->create(['season_id' => $this->season->id, 'name' => 'Kilby Reeves']);

        expect($this->draft->makePick($this->season, 'Jyrki', 'kilby')->castaway_id)->toBe($kilby->id);
    });

    it('rejects an unknown manager and says who is playing', function () {
        expect(fn () => $this->draft->makePick($this->season, 'Probst', 'Castaway 1'))
            ->toThrow(DraftException::class, 'No manager called "Probst". This season has: Jyrki, Rein.');
    });

    it('hands the clock straight back when the round turns over', function () {
        $this->draft->makePick($this->season, 'Jyrki', 'Castaway 1');
        $this->draft->makePick($this->season, 'Rein', 'Castaway 2');

        // Round 2 starts the same way round 1 did, so Jyrki is up again.
        expect($this->draft->onTheClock($this->season)->name)->toBe('Jyrki');

        $this->draft->makePick($this->season, 'Jyrki', 'Castaway 3');

        expect($this->draft->onTheClock($this->season)->name)->toBe('Rein');
    });

    it('refuses to let a manager pick twice in a row', function () {
        $this->draft->makePick($this->season, 'Jyrki', 'Castaway 1');

        expect(fn () => $this->draft->makePick($this->season, 'Jyrki', 'Castaway 2'))
            ->toThrow(DraftException::class, "It is Rein's pick, not Jyrki's.");
    });

    it('refuses to run past the end of the draft', function () {
        $order = $this->draft->pickOrder($this->season);

        foreach ($order as $index => $slot) {
            $this->draft->makePick($this->season, $slot['manager']->name, 'Castaway '.($index + 1));
        }

        expect($this->draft->isComplete($this->season))->toBeTrue()
            ->and(fn () => $this->draft->makePick($this->season, 'Jyrki', 'Castaway 21'))
            ->toThrow(DraftException::class, 'The draft is already complete.');
    });

    it('leaves exactly one castaway undrafted', function () {
        foreach ($this->draft->pickOrder($this->season) as $index => $slot) {
            $this->draft->makePick($this->season, $slot['manager']->name, 'Castaway '.($index + 1));
        }

        expect($this->draft->availableCastaways($this->season))->toHaveCount(1);
    });

    it('drops a castaway out of the available list once taken', function () {
        expect($this->draft->availableCastaways($this->season))->toHaveCount(21);

        $this->draft->makePick($this->season, 'Jyrki', 'Castaway 1');

        expect($this->draft->availableCastaways($this->season))->toHaveCount(20);
    });
});

describe('undo', function () {
    it('removes the most recent pick and hands the clock back', function () {
        $this->draft->makePick($this->season, 'Jyrki', 'Castaway 1');
        $this->draft->makePick($this->season, 'Rein', 'Castaway 2');

        $undone = $this->draft->undoLastPick($this->season);

        expect($undone->pick_number)->toBe(2)
            ->and($this->draft->nextPickNumber($this->season))->toBe(2)
            ->and($this->draft->onTheClock($this->season)->name)->toBe('Rein')
            ->and($this->draft->availableCastaways($this->season))->toHaveCount(20);
    });

    it('refuses when nothing has been picked', function () {
        $this->draft->undoLastPick($this->season);
    })->throws(DraftException::class, 'No picks have been made yet.');
});

describe('locking when the season starts', function () {
    beforeEach(function () {
        $this->draft->makePick($this->season, 'Jyrki', 'Castaway 1');
    });

    it('is unlocked while no episode has been recorded', function () {
        expect($this->draft->isLocked($this->season))->toBeFalse();
    });

    it('locks the moment the first episode is recorded', function () {
        Episode::factory()->create(['season_id' => $this->season->id, 'number' => 1]);

        expect($this->draft->isLocked($this->season->fresh()))->toBeTrue();
    });

    it('refuses a pick once the season has started', function () {
        Episode::factory()->create(['season_id' => $this->season->id, 'number' => 1]);

        expect(fn () => $this->draft->makePick($this->season->fresh(), 'Rein', 'Castaway 2'))
            ->toThrow(DraftException::class, 'The season has started, so the draft is locked');
    });

    it('refuses an undo once the season has started, so rosters cannot move under a score', function () {
        Episode::factory()->create(['season_id' => $this->season->id, 'number' => 1]);

        expect(fn () => $this->draft->undoLastPick($this->season->fresh()))
            ->toThrow(DraftException::class, 'the draft is locked');
    });

    it('refuses to re-order the draft once the season has started', function () {
        Episode::factory()->create(['season_id' => $this->season->id, 'number' => 1]);

        expect(fn () => $this->draft->setDraftOrder($this->season->fresh(), 'Rein'))
            ->toThrow(DraftException::class, 'the draft is locked');
    });

    it('reopens when the episode is deleted, because the lock is derived', function () {
        $episode = Episode::factory()->create(['season_id' => $this->season->id, 'number' => 1]);

        expect($this->draft->isLocked($this->season->fresh()))->toBeTrue();

        $episode->delete();

        expect($this->draft->isLocked($this->season->fresh()))->toBeFalse();

        // And the draft is usable again.
        $this->draft->makePick($this->season->fresh(), 'Rein', 'Castaway 2');

        expect($this->season->draftPicks()->count())->toBe(2);
    });
});

describe('the coin flip', function () {
    it('gives the named manager the first pick', function () {
        $this->draft->setDraftOrder($this->season, 'Rein');

        expect($this->rein->fresh()->draft_slot)->toBe(1)
            ->and($this->jyrki->fresh()->draft_slot)->toBe(2)
            ->and($this->draft->onTheClock($this->season)->name)->toBe('Rein');
    });

    it('survives swapping two managers head-on against the unique slot index', function () {
        $this->draft->setDraftOrder($this->season, 'Rein');
        $this->draft->setDraftOrder($this->season, 'Jyrki');

        expect($this->jyrki->fresh()->draft_slot)->toBe(1)
            ->and($this->rein->fresh()->draft_slot)->toBe(2);
    });

    it('locks the order once a pick has been made', function () {
        $this->draft->makePick($this->season, 'Jyrki', 'Castaway 1');

        $this->draft->setDraftOrder($this->season, 'Rein');
    })->throws(DraftException::class, 'Picks have already been made, so the draft order is locked.');
});
