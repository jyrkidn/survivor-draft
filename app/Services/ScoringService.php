<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CastawayScore;
use App\Data\ManagerStanding;
use App\Data\Scoreboard;
use App\Enums\EventType;
use App\Models\Castaway;
use App\Models\Event;
use App\Models\Manager;
use App\Models\ScoringRule;
use App\Models\Season;
use Illuminate\Support\Collection;

/**
 * Derives every point total in the application.
 *
 * Nothing stores a score. Given the append-only event log and the number of
 * aired episodes, this class recomputes castaway points, manager totals and the
 * standings from scratch on every read. Deleting a bad event, or changing a
 * scoring rule, therefore re-scores the whole season with no migration.
 */
final class ScoringService
{
    /**
     * Rank given to a castaway who is still in the game — better than any
     * finish, since they can still win.
     */
    private const STILL_IN_RANK = 0;

    /**
     * Ranks for the eliminated are seeded from here so that they always sort
     * behind the four recorded placements, later exits first.
     */
    private const ELIMINATED_RANK_BASE = 1000;

    /**
     * The scoring values in force for a season, keyed by event type.
     *
     * Falls back to the type's built-in default when a season has no rule row,
     * so a half-seeded season still scores sensibly.
     *
     * @return array<string, int>
     */
    public function rules(Season $season): array
    {
        $stored = ScoringRule::query()
            ->where('season_id', $season->id)
            ->get()
            ->mapWithKeys(fn (ScoringRule $rule): array => [$rule->type->value => $rule->points])
            ->all();

        $rules = [];

        foreach (EventType::cases() as $type) {
            $rules[$type->value] = $stored[$type->value] ?? $type->defaultPoints();
        }

        return $rules;
    }

    /**
     * Write the out-of-the-box scoring values for a season, leaving any rule
     * that has already been tuned alone.
     */
    public function seedDefaultRules(Season $season): void
    {
        foreach (EventType::cases() as $type) {
            ScoringRule::query()->firstOrCreate(
                ['season_id' => $season->id, 'type' => $type],
                ['points' => $type->defaultPoints(), 'label' => $type->label()],
            );
        }
    }

    /**
     * Score every castaway in the season, drafted or not.
     *
     * Passing `$throughEpisode` scores the season as it stood at the end of that
     * episode, which is how the gap history is built.
     *
     * @return array<int, CastawayScore> Keyed by castaway id.
     */
    public function scoreCastaways(Season $season, ?int $throughEpisode = null): array
    {
        $castaways = Castaway::query()
            ->where('season_id', $season->id)
            ->orderBy('name')
            ->get();

        $events = Event::query()
            ->where('season_id', $season->id)
            ->orderBy('episode_number')
            ->orderBy('id')
            ->get();

        return $this->derive(
            $castaways,
            $events,
            $this->ownersByCastawayId($season),
            $this->rules($season),
            $season->episodes()->pluck('number')->all(),
            $throughEpisode,
        );
    }

    /**
     * The gap between the two managers after each aired episode.
     *
     * Recomputed from scratch per episode rather than accumulated, so it stays
     * correct after an event is deleted or a scoring rule changes. The gap is
     * signed from the first draft slot's point of view: positive means slot 1 is
     * ahead.
     *
     * @return array<int, array{episode: int, gap: int, leader_slot: int|null, totals: array<int, int>}>
     */
    public function gapByEpisode(Season $season): array
    {
        $managers = $season->managers()->get();

        if ($managers->count() < 2) {
            return [];
        }

        $castaways = Castaway::query()->where('season_id', $season->id)->get();
        $events = Event::query()
            ->where('season_id', $season->id)
            ->orderBy('episode_number')
            ->orderBy('id')
            ->get();
        $owners = $this->ownersByCastawayId($season);
        $rules = $this->rules($season);
        $episodeNumbers = $season->episodes()->pluck('number')->all();

        sort($episodeNumbers);

        $history = [];

        foreach ($episodeNumbers as $episodeNumber) {
            $scores = $this->derive($castaways, $events, $owners, $rules, $episodeNumbers, $episodeNumber);

            $totals = [];

            foreach ($managers as $manager) {
                $totals[$manager->draft_slot] = array_sum(array_map(
                    fn (CastawayScore $score): int => $score->totalPoints,
                    array_filter($scores, fn (CastawayScore $s): bool => $s->owner?->id === $manager->id),
                ));
            }

            $first = $managers[0]->draft_slot;
            $second = $managers[1]->draft_slot;
            $gap = $totals[$first] - $totals[$second];

            $history[] = [
                'episode' => $episodeNumber,
                'gap' => $gap,
                'leader_slot' => $gap === 0 ? null : ($gap > 0 ? $first : $second),
                'totals' => $totals,
            ];
        }

        return $history;
    }

    /**
     * @param  Collection<int, Castaway>  $castaways
     * @param  Collection<int, Event>  $events
     * @param  array<int, Manager>  $owners
     * @param  array<string, int>  $rules
     * @param  array<int, int>  $episodeNumbers
     * @return array<int, CastawayScore>
     */
    private function derive(
        Collection $castaways,
        Collection $events,
        array $owners,
        array $rules,
        array $episodeNumbers,
        ?int $throughEpisode = null,
    ): array {
        if ($throughEpisode !== null) {
            $episodeNumbers = array_filter($episodeNumbers, fn (int $n): bool => $n <= $throughEpisode);
            $events = $events->filter(fn (Event $event): bool => $event->episode_number <= $throughEpisode);
        }

        $episodesAired = count($episodeNumbers);
        $eventsByCastaway = $events->groupBy('castaway_id');

        $scores = [];

        foreach ($castaways as $castaway) {
            $scores[$castaway->id] = $this->buildScore(
                $castaway,
                $owners[$castaway->id] ?? null,
                $eventsByCastaway->get($castaway->id) ?? new Collection,
                $rules,
                $episodesAired,
            );
        }

        return $scores;
    }

    /**
     * Score a single castaway. Convenience wrapper; prefer scoreCastaways() when
     * you need more than one.
     */
    public function scoreCastaway(Castaway $castaway): CastawayScore
    {
        $season = $castaway->season;

        return $this->scoreCastaways($season)[$castaway->id]
            ?? $this->buildScore($castaway, null, new Collection, $this->rules($season), $season->episodesAired());
    }

    /**
     * The full derived picture: standings best first, plus the undrafted
     * castaways and who is still in.
     */
    public function scoreboard(Season $season): Scoreboard
    {
        $scores = $this->scoreCastaways($season);

        $standings = $season->managers()->get()
            ->map(function (Manager $manager) use ($scores): ManagerStanding {
                $roster = array_values(array_filter(
                    $scores,
                    fn (CastawayScore $score): bool => $score->owner?->id === $manager->id,
                ));

                usort($roster, fn (CastawayScore $a, CastawayScore $b): int => $b->totalPoints <=> $a->totalPoints);

                return new ManagerStanding(
                    manager: $manager,
                    roster: $roster,
                    totalPoints: array_sum(array_map(fn (CastawayScore $s): int => $s->totalPoints, $roster)),
                    bestFinishRank: $roster === []
                        ? PHP_INT_MAX
                        : min(array_map(fn (CastawayScore $s): int => $s->finishRank, $roster)),
                );
            })
            ->all();

        // Most points wins; the tie-break is the highest-placing castaway.
        usort($standings, fn (ManagerStanding $a, ManagerStanding $b): int => [$b->totalPoints, $a->bestFinishRank]
            <=> [$a->totalPoints, $b->bestFinishRank]);

        $undrafted = array_values(array_filter($scores, fn (CastawayScore $s): bool => $s->owner === null));
        usort($undrafted, fn (CastawayScore $a, CastawayScore $b): int => $b->totalPoints <=> $a->totalPoints);

        $stillIn = array_values(array_filter($scores, fn (CastawayScore $s): bool => $s->isStillIn()));
        usort($stillIn, fn (CastawayScore $a, CastawayScore $b): int => $b->totalPoints <=> $a->totalPoints);

        return new Scoreboard(
            season: $season,
            episodesAired: $season->episodesAired(),
            standings: $standings,
            undrafted: $undrafted,
            stillIn: $stillIn,
        );
    }

    /**
     * Castaways with no elimination event before the given episode — that is,
     * everyone alive when the episode starts.
     *
     * @return Collection<int, Castaway>
     */
    public function livingAtStartOfEpisode(Season $season, int $episodeNumber): Collection
    {
        $eliminated = Event::query()
            ->where('season_id', $season->id)
            ->where('episode_number', '<', $episodeNumber)
            ->whereIn('type', array_map(fn (EventType $t): string => $t->value, EventType::eliminations()))
            ->pluck('castaway_id')
            ->unique()
            ->all();

        return Castaway::query()
            ->where('season_id', $season->id)
            ->when($eliminated !== [], fn ($query) => $query->whereNotIn('id', $eliminated))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  array<string, int>  $rules
     */
    private function buildScore(
        Castaway $castaway,
        ?Manager $owner,
        Collection $events,
        array $rules,
        int $episodesAired,
    ): CastawayScore {
        $exitEpisode = $events
            ->filter(fn (Event $event): bool => $event->type->isElimination())
            ->min('episode_number');

        $exitEpisode = $exitEpisode === null ? null : (int) $exitEpisode;

        $placement = $events
            ->first(fn (Event $event): bool => $event->type->isPlacement())?->type;

        // A castaway banks a point for every episode they start and are not
        // eliminated in. Survive to the end and they bank the full count.
        $survivedEpisodes = $exitEpisode === null
            ? $episodesAired
            : max(0, min($exitEpisode - 1, $episodesAired));

        $survivalPoints = $survivedEpisodes * $rules[EventType::Survived->value];

        $eventPoints = 0;
        $breakdown = [];

        foreach ($events as $event) {
            $points = $event->points ?? $rules[$event->type->value];
            $eventPoints += $points;

            $breakdown[] = [
                'episode' => $event->episode_number,
                'type' => $event->type->value,
                'label' => $event->type->label(),
                'points' => $points,
                'note' => $event->note,
                'event_id' => $event->id,
            ];
        }

        return new CastawayScore(
            castaway: $castaway,
            owner: $owner,
            status: $exitEpisode === null ? 'in' : 'out',
            exitEpisode: $exitEpisode,
            placement: $placement,
            survivedEpisodes: $survivedEpisodes,
            survivalPoints: $survivalPoints,
            eventPoints: $eventPoints,
            totalPoints: $survivalPoints + $eventPoints,
            finishRank: $this->finishRank($placement, $exitEpisode),
            breakdown: $breakdown,
        );
    }

    /**
     * A sortable "how far did they get" key, lower is better: recorded
     * placements first, then the eliminated ordered by how long they lasted.
     */
    private function finishRank(?EventType $placement, ?int $exitEpisode): int
    {
        if ($placement !== null) {
            return (int) $placement->placementRank();
        }

        if ($exitEpisode === null) {
            return self::STILL_IN_RANK;
        }

        return self::ELIMINATED_RANK_BASE - $exitEpisode;
    }

    /**
     * @return array<int, Manager> Manager keyed by the castaway id they drafted.
     */
    private function ownersByCastawayId(Season $season): array
    {
        return $season->draftPicks()->with('manager')->get()
            ->mapWithKeys(fn ($pick): array => [$pick->castaway_id => $pick->manager])
            ->all();
    }
}
