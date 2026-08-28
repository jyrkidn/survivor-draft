<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\CastawayScore;
use App\Http\Presenters\LeaguePresenter;
use App\Services\ScoringService;
use App\Services\SeasonService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cast grid, which doubles as the draft-night reference.
 */
final class CastController extends Controller
{
    public function __construct(
        private readonly SeasonService $seasons,
        private readonly ScoringService $scoring,
    ) {}

    public function show(): Response
    {
        $season = $this->seasons->resolve();
        $scores = $this->scoring->scoreCastaways($season);

        $draftOrder = $season->draftPicks()->pluck('pick_number', 'castaway_id')->all();
        $initials = LeaguePresenter::initials($season->managers()->get());

        // Sorted by tribe, then by where they went in the draft, then by name.
        // One composite key rather than a multi-column sort, so the ordering is
        // unambiguous. Filters subset this order on the client; they never
        // reorder it.
        $cards = collect($scores)
            ->sortBy(fn (CastawayScore $score): string => sprintf(
                '%s|%06d|%s',
                $score->castaway->tribe ?? "\u{FFFF}",
                min($draftOrder[$score->castaway->id] ?? 999999, 999999),
                $score->castaway->name,
            ))
            ->map(fn (CastawayScore $score): array => LeaguePresenter::card($score, $initials))
            ->values()
            ->all();

        return Inertia::render('Cast', [
            'season' => [
                'number' => $season->number,
                'title' => $season->title,
            ],
            'castaways' => $cards,
            'tribes' => collect($cards)->pluck('tribe')->filter()->unique()->values()->all(),
            'aliveCount' => count(array_filter($cards, fn (array $c): bool => $c['status'] === 'in')),
            'outCount' => count(array_filter($cards, fn (array $c): bool => $c['status'] === 'out')),
        ]);
    }
}
