<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\CastawayScore;
use App\Data\ManagerStanding;
use App\Http\Presenters\LeaguePresenter;
use App\Services\ScoringService;
use App\Services\SeasonService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Answers "am I winning" in the first 200px, then explains itself.
 */
final class ScoreboardController extends Controller
{
    public function __construct(
        private readonly SeasonService $seasons,
        private readonly ScoringService $scoring,
    ) {}

    public function show(): Response
    {
        $season = $this->seasons->resolve();
        $board = $this->scoring->scoreboard($season);

        $standings = $board->standings;
        $leader = count($standings) > 0 ? $standings[0] : null;
        $chaser = count($standings) > 1 ? $standings[1] : null;

        $gap = $leader !== null && $chaser !== null
            ? $leader->totalPoints - $chaser->totalPoints
            : 0;

        // Bars scale to the leader, so the leader is always full width.
        $best = max(1, $leader === null ? 1 : $leader->totalPoints);

        return Inertia::render('Scoreboard', [
            'season' => [
                'number' => $season->number,
                'title' => $season->title,
                'premiere_label' => $season->premiere_date?->format('j M'),
            ],
            'episodesAired' => $board->episodesAired,
            'seasonStarted' => $board->episodesAired > 0,
            'verdict' => [
                // A dead heat reads LEVEL rather than a signed zero.
                'level' => $gap === 0,
                'gap' => abs($gap),
                // A zero gap covers both a genuine tie and a season with fewer
                // than two managers, so the leader is only read when it exists.
                'leader_name' => $gap === 0 ? null : $leader->manager->name,
                'leader_slot' => $gap === 0 ? null : $leader->manager->draft_slot,
            ],
            // Ordered by draft slot, not by rank: the totals block is a fixed
            // A-then-B reading, and the bars carry the comparison.
            'totals' => collect($standings)
                ->sortBy(fn (ManagerStanding $s): int => $s->manager->draft_slot)
                ->map(fn (ManagerStanding $standing): array => [
                    ...LeaguePresenter::manager($standing->manager),
                    'points' => $standing->totalPoints,
                    'bar_percent' => (int) round($standing->totalPoints / $best * 100),
                ])->values()->all(),
            'gapByEpisode' => $this->scoring->gapByEpisode($season),
            'rosters' => collect($standings)
                ->sortBy(fn (ManagerStanding $s): int => $s->manager->draft_slot)
                ->map(fn (ManagerStanding $standing): array => [
                    ...LeaguePresenter::manager($standing->manager),
                    'alive' => count(array_filter(
                        $standing->roster,
                        fn (CastawayScore $score): bool => $score->isStillIn(),
                    )),
                    'castaways' => array_map(
                        fn (CastawayScore $score): array => LeaguePresenter::rosterRow($score),
                        $standing->roster,
                    ),
                ])->values()->all(),
        ]);
    }
}
