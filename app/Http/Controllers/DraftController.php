<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\LeagueException;
use App\Http\Presenters\LeaguePresenter;
use App\Models\Castaway;
use App\Models\DraftPick;
use App\Models\Manager;
use App\Models\Season;
use App\Services\DraftService;
use App\Services\SeasonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The live draft room. Both managers pick from one laptop, so the pick belongs
 * to whoever the order says is on the clock rather than to whoever is logged in.
 *
 * The database is still the real guard: a double submit loses the race on
 * `unique(season_id, pick_number)` and comes back as "that pick was already
 * made" rather than corrupting the board.
 */
final class DraftController extends Controller
{
    public function __construct(
        private readonly SeasonService $seasons,
        private readonly DraftService $draft,
    ) {}

    public function show(Request $request): Response
    {
        $season = $this->seasons->resolve();
        $onTheClock = $this->draft->onTheClock($season);

        return Inertia::render('Draft', [
            'season' => [
                'number' => $season->number,
                'title' => $season->title,
                'premiere_date' => $season->premiere_date?->toDateString(),
                'premiere_label' => $season->premiere_date?->format('j M Y'),
                'roster_size' => $season->roster_size,
            ],
            'managers' => $season->managers()->withCount('draftPicks')->get()
                ->map(fn (Manager $manager): array => [
                    ...LeaguePresenter::manager($manager),
                    'picks_made' => $manager->draft_picks_count,
                ])->all(),
            'picks' => $season->draftPicks()->with(['manager', 'castaway'])->get()
                ->map(fn (DraftPick $pick): array => LeaguePresenter::pick($pick))->all(),
            'onTheClock' => $onTheClock === null ? null : LeaguePresenter::manager($onTheClock),
            'nextPickNumber' => $this->draft->isComplete($season) ? null : $this->draft->nextPickNumber($season),
            'totalPicks' => $season->totalDraftPicks(),
            'rounds' => $season->draftRounds(),
            'isComplete' => $this->draft->isComplete($season),
            'isLocked' => $this->draft->isLocked($season),
            'episodesAired' => $season->episodesAired(),
            'available' => $this->draft->availableCastaways($season)
                ->sortBy([['tribe', 'asc'], ['name', 'asc']])
                ->values()
                ->map(fn (Castaway $castaway): array => LeaguePresenter::availableCastaway($castaway))
                ->all(),
            'castLocked' => $season->castaways()->count(),
            'daysToPremiere' => $this->daysToPremiere($season),
        ]);
    }

    /**
     * Both managers draft from the same laptop, so the pick belongs to whoever
     * the pick order says is on the clock — not to whoever happens to be
     * logged in.
     */
    public function pick(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'castaway_id' => ['required', 'integer'],
        ]);

        $season = $this->seasons->resolve();
        $manager = $this->draft->onTheClock($season);

        if ($manager === null) {
            return back()->with('error', 'The draft is already complete.');
        }

        $castaway = Castaway::query()
            ->where('season_id', $season->id)
            ->whereKey($validated['castaway_id'])
            ->first();

        if ($castaway === null) {
            return back()->with('error', 'That castaway is not in this season.');
        }

        try {
            $this->draft->makePick($season, $manager->name, $castaway->name);
        } catch (LeagueException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "{$manager->name} takes {$castaway->name}.");
    }

    public function undo(Request $request): RedirectResponse
    {
        $season = $this->seasons->resolve();

        try {
            $undone = $this->draft->undoLastPick($season);
        } catch (LeagueException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Pick {$undone->pick_number} undone — {$undone->castaway->name} is back in.");
    }

    private function daysToPremiere(Season $season): ?int
    {
        if ($season->premiere_date === null) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($season->premiere_date->startOfDay(), false));
    }
}
