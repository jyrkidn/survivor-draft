<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\EpisodeRecording;
use App\Data\EpisodeRecordingResult;
use App\Enums\EventType;
use App\Exceptions\AmbiguousCastawayException;
use App\Exceptions\CastawayNotFoundException;
use App\Exceptions\RecordingException;
use App\Models\Castaway;
use App\Models\Episode;
use App\Models\Event;
use App\Models\Season;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records one episode's worth of events in a single call.
 *
 * The important property is that it is idempotent per episode: every existing
 * event for the episode number is deleted and re-inserted, so calling it twice
 * corrects rather than duplicates. That is what makes it safe to enter results
 * live while watching, and to fix a typo by simply saying the whole episode
 * again.
 */
final class EpisodeRecorder
{
    public function __construct(
        private readonly CastawayResolver $resolver,
        private readonly ScoringService $scoring,
    ) {}

    public function record(Season $season, EpisodeRecording $recording): EpisodeRecordingResult
    {
        if ($recording->episode < 1) {
            throw RecordingException::because('Episode numbers start at 1.');
        }

        return DB::transaction(function () use ($season, $recording): EpisodeRecordingResult {
            $episode = $this->upsertEpisode($season, $recording);

            // Wipe the episode clean before re-inserting, so a second call for
            // the same episode replaces rather than stacks.
            Event::query()
                ->where('season_id', $season->id)
                ->where('episode_number', $recording->episode)
                ->delete();

            $cast = Castaway::query()->where('season_id', $season->id)->orderBy('name')->get();

            // Who was alive when the episode started, before this episode's
            // eliminations are applied.
            $living = $this->scoring->livingAtStartOfEpisode($season, $recording->episode);
            $livingIds = $living->pluck('id')->all();

            /** @var array<int, Event> $events */
            $events = [];
            /** @var array<int, string> $warnings */
            $warnings = [];
            /** @var array<string, true> $seen */
            $seen = [];

            if ($recording->merge) {
                foreach ($living as $castaway) {
                    $this->addEvent($events, $seen, $season, $recording->episode, $castaway, EventType::MadeMerge);
                }
            }

            foreach (EventType::recordableInBulk() as $type) {
                foreach ($recording->namesFor($type) as $name) {
                    $targets = $this->targetsFor($season, $cast, $living, $type, $name, $warnings);

                    foreach ($targets as $castaway) {
                        if (! in_array($castaway->id, $livingIds, true)) {
                            $warnings[] = sprintf(
                                '%s was already out before episode %d, but %s was recorded anyway. Check the episode number.',
                                $castaway->name,
                                $recording->episode,
                                $type->label(),
                            );
                        }

                        $this->addEvent($events, $seen, $season, $recording->episode, $castaway, $type);
                    }
                }
            }

            return new EpisodeRecordingResult(
                episode: $episode,
                events: $events,
                warnings: array_values(array_unique($warnings)),
                scoreboard: $this->scoring->scoreboard($season->fresh()),
            );
        });
    }

    /**
     * Record the finale bonuses, replacing any placements already on the books.
     *
     * @param  array<string, string|null>  $placements  Placement type value => castaway name.
     * @return array<int, Event>
     *
     * @throws RecordingException
     */
    public function setFinalPlacements(Season $season, array $placements): array
    {
        $episodeNumber = (int) $season->episodes()->max('number');

        if ($episodeNumber === 0) {
            throw RecordingException::because(
                'No episodes have been recorded yet, so there is nowhere to hang the finale bonuses.'
            );
        }

        $cast = Castaway::query()->where('season_id', $season->id)->orderBy('name')->get();

        return DB::transaction(function () use ($season, $cast, $placements, $episodeNumber): array {
            // Placements replace rather than accumulate, so a corrected finale
            // never leaves a stale winner behind.
            Event::query()
                ->where('season_id', $season->id)
                ->whereIn('type', array_map(fn (EventType $t): string => $t->value, EventType::placements()))
                ->delete();

            $events = [];

            foreach (EventType::placements() as $type) {
                $name = $placements[$type->value] ?? null;

                if ($name === null || trim($name) === '') {
                    continue;
                }

                $castaway = $this->resolver->resolveIn($cast, $name);

                $event = Event::query()->create([
                    'season_id' => $season->id,
                    'episode_number' => $episodeNumber,
                    'castaway_id' => $castaway->id,
                    'type' => $type,
                ]);

                $event->setRelation('castaway', $castaway);

                $events[] = $event;
            }

            return $events;
        });
    }

    private function upsertEpisode(Season $season, EpisodeRecording $recording): Episode
    {
        $episode = Episode::query()->firstOrNew([
            'season_id' => $season->id,
            'number' => $recording->episode,
        ]);

        // Only overwrite the descriptive fields when this call actually supplied
        // them, so a quick correcting call does not blank out the title.
        if ($recording->title !== null) {
            $episode->title = $recording->title;
        }

        if ($recording->airDate !== null) {
            $episode->air_date = CarbonImmutable::parse($recording->airDate);
        }

        if ($recording->notes !== null) {
            $episode->notes = $recording->notes;
        }

        $episode->save();

        return $episode;
    }

    /**
     * Who a single entry refers to.
     *
     * Tribe immunity is the interesting case: the entry is matched against tribe
     * names first, so "Lagi won immunity" awards every living member of Lagi,
     * and only falls back to treating the entry as a castaway name if no tribe
     * has that name.
     *
     * @param  Collection<int, Castaway>  $cast
     * @param  Collection<int, Castaway>  $living
     * @param  array<int, string>  $warnings
     * @return array<int, Castaway>
     */
    private function targetsFor(
        Season $season,
        Collection $cast,
        Collection $living,
        EventType $type,
        string $name,
        array &$warnings,
    ): array {
        if ($type === EventType::TribeImmunity) {
            $tribeMembers = $living->filter(
                fn (Castaway $castaway): bool => $castaway->tribe !== null
                    && Str::lower($castaway->tribe) === Str::lower(trim($name)),
            )->values();

            if ($tribeMembers->isNotEmpty()) {
                return $tribeMembers->all();
            }

            $isKnownTribe = $cast->contains(
                fn (Castaway $castaway): bool => $castaway->tribe !== null
                    && Str::lower($castaway->tribe) === Str::lower(trim($name)),
            );

            if ($isKnownTribe) {
                $warnings[] = "No living members of tribe \"{$name}\" to award tribe immunity to.";

                return [];
            }
        }

        try {
            return [$this->resolver->resolveIn($cast, $name)];
        } catch (CastawayNotFoundException|AmbiguousCastawayException $exception) {
            $warnings[] = $exception->getMessage().' Skipped.';

            return [];
        }
    }

    /**
     * @param  array<int, Event>  $events
     * @param  array<string, true>  $seen
     */
    private function addEvent(
        array &$events,
        array &$seen,
        Season $season,
        int $episodeNumber,
        Castaway $castaway,
        EventType $type,
    ): void {
        $key = $castaway->id.':'.$type->value;

        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;

        $event = Event::query()->create([
            'season_id' => $season->id,
            'episode_number' => $episodeNumber,
            'castaway_id' => $castaway->id,
            'type' => $type,
        ]);

        $event->setRelation('castaway', $castaway);

        $events[] = $event;
    }
}
