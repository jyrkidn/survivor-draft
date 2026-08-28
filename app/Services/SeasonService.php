<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SeasonExistsException;
use App\Exceptions\SeasonNotFoundException;
use App\Models\Manager;
use App\Models\Season;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seasons, and which one every other tool means when it is not told.
 *
 * Season 52 should be one call rather than a rebuild, so nothing anywhere
 * hardcodes a season, a manager count or a roster size.
 */
final class SeasonService
{
    /**
     * The season a caller meant: the one they named, or the active one.
     *
     * @throws SeasonNotFoundException
     */
    public function resolve(?int $number = null): Season
    {
        if ($number !== null) {
            $season = Season::query()->where('number', $number)->first();

            if ($season === null) {
                throw SeasonNotFoundException::numbered($number);
            }

            return $season;
        }

        $active = Season::query()->where('is_active', true)->first();

        if ($active === null) {
            throw SeasonNotFoundException::active();
        }

        return $active;
    }

    /**
     * @return Collection<int, Season>
     */
    public function all(): Collection
    {
        return Season::query()->orderByDesc('number')->withCount(['castaways', 'episodes', 'draftPicks'])->get();
    }

    /**
     * Stand up a new season, its managers and its scoring rules in one go.
     *
     * @param  array<int, string>  $managerNames  In draft-slot order; the coin flip can reorder them later.
     *
     * @throws SeasonExistsException
     */
    public function create(
        int $number,
        ?string $title = null,
        ?string $premiereDate = null,
        int $rosterSize = 0,
        array $managerNames = [],
        bool $makeActive = true,
    ): Season {
        if (Season::query()->where('number', $number)->exists()) {
            throw SeasonExistsException::because("Season {$number} already exists.");
        }

        return DB::transaction(function () use ($number, $title, $premiereDate, $rosterSize, $managerNames, $makeActive): Season {
            $season = Season::query()->create([
                'number' => $number,
                'title' => $title,
                'premiere_date' => $premiereDate,
                'roster_size' => $rosterSize,
                'is_active' => false,
            ]);

            foreach (array_values($managerNames) as $index => $name) {
                Manager::query()->create([
                    'season_id' => $season->id,
                    'name' => $name,
                    'draft_slot' => $index + 1,
                ]);
            }

            app(ScoringService::class)->seedDefaultRules($season);

            if ($makeActive) {
                $this->setActive($season);
            }

            return $season->refresh();
        });
    }

    /**
     * Exactly one season is active at a time.
     */
    public function setActive(Season $season): Season
    {
        DB::transaction(function () use ($season): void {
            Season::query()->where('is_active', true)->update(['is_active' => false]);
            $season->update(['is_active' => true]);
        });

        return $season->refresh();
    }
}
