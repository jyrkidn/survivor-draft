<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DraftException;
use App\Models\Castaway;
use App\Models\DraftPick;
use App\Models\Manager;
use App\Models\Season;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The draft: whose turn it is, making a pick, and taking one back.
 *
 * The order is straight alternating, not a snake — every round runs through the
 * managers in draft-slot order, so nobody ever picks twice in a row.
 *
 * All three surfaces call this. If turn validation were reimplemented in a
 * Filament action it would drift from the MCP tool, and the drift would surface
 * mid-draft.
 */
final class DraftService
{
    public function __construct(private readonly CastawayResolver $castaways) {}

    /**
     * The full pick order for the season, pick 1 to the last.
     *
     * Round 1 runs slot 1 to slot N, round 2 runs back the other way, and so on.
     * With two managers that gives slot 1 picks 1, 4, 5, 8 and slot 2 picks
     * 2, 3, 6, 7 — which is exactly why the coin-flip winner gets to choose
     * between them.
     *
     * @return array<int, array{pick_number: int, round: int, manager: Manager}>
     */
    public function pickOrder(Season $season): array
    {
        $managers = $season->managers()->get();
        $count = $managers->count();

        if ($count === 0) {
            return [];
        }

        $order = [];

        foreach (range(1, $season->draftRounds() * $count) as $pickNumber) {
            $round = intdiv($pickNumber - 1, $count) + 1;
            $indexInRound = ($pickNumber - 1) % $count;

            $order[] = [
                'pick_number' => $pickNumber,
                'round' => $round,
                'manager' => $managers[$indexInRound],
            ];
        }

        return $order;
    }

    /**
     * Who is on the clock, or null once every pick is in.
     */
    public function onTheClock(Season $season): ?Manager
    {
        $next = $this->nextPickNumber($season);
        $order = $this->pickOrder($season);

        return $order[$next - 1]['manager'] ?? null;
    }

    public function nextPickNumber(Season $season): int
    {
        return (int) $season->draftPicks()->max('pick_number') + 1;
    }

    public function isComplete(Season $season): bool
    {
        return $this->nextPickNumber($season) > $season->totalDraftPicks();
    }

    /**
     * The draft closes the moment the season starts.
     *
     * Derived from the aired episodes rather than stored as a flag, so it
     * cannot fall out of step with them — and so deleting a mistakenly recorded
     * episode reopens the draft on its own.
     */
    public function isLocked(Season $season): bool
    {
        return $season->episodesAired() > 0;
    }

    /**
     * Castaways nobody has taken yet.
     *
     * @return Collection<int, Castaway>
     */
    public function availableCastaways(Season $season): Collection
    {
        $taken = $season->draftPicks()->pluck('castaway_id')->all();

        return Castaway::query()
            ->where('season_id', $season->id)
            ->when($taken !== [], fn ($query) => $query->whereNotIn('id', $taken))
            ->orderBy('name')
            ->get();
    }

    /**
     * Record one pick, validated against the alternating pick order.
     *
     * @throws DraftException
     */
    public function makePick(Season $season, string $managerName, string $castawayName): DraftPick
    {
        $this->assertUnlocked($season);

        if ($this->isComplete($season)) {
            throw DraftException::because('The draft is already complete.');
        }

        $manager = $this->resolveManager($season, $managerName);
        $castaway = $this->castaways->resolve($season, $castawayName);

        $existing = $season->draftPicks()->where('castaway_id', $castaway->id)->with('manager')->first();

        if ($existing !== null) {
            throw DraftException::because(
                "{$castaway->name} was already taken by {$existing->manager->name} at pick {$existing->pick_number}."
            );
        }

        $pickNumber = $this->nextPickNumber($season);
        $order = $this->pickOrder($season)[$pickNumber - 1];

        if ($order['manager']->id !== $manager->id) {
            throw DraftException::because(
                "It is {$order['manager']->name}'s pick, not {$manager->name}'s. Pick {$pickNumber} belongs to {$order['manager']->name}."
            );
        }

        try {
            return DraftPick::query()->create([
                'season_id' => $season->id,
                'pick_number' => $pickNumber,
                'round' => $order['round'],
                'manager_id' => $manager->id,
                'castaway_id' => $castaway->id,
            ]);
        } catch (QueryException $exception) {
            // Two devices submitting at once: the unique index on
            // (season_id, pick_number) makes one of them lose the race rather
            // than corrupting the board.
            if ($this->isUniqueViolation($exception)) {
                throw DraftException::because("Pick {$pickNumber} was already made. Reload the board.");
            }

            throw $exception;
        }
    }

    /**
     * Remove the most recent pick. Draft night is chaotic.
     *
     * @throws DraftException
     */
    public function undoLastPick(Season $season): DraftPick
    {
        $this->assertUnlocked($season);

        // reorder() first: the relation is ordered ascending, and a second
        // orderBy would be appended behind it rather than replacing it.
        $last = $season->draftPicks()->with(['manager', 'castaway'])
            ->reorder('pick_number', 'desc')
            ->first();

        if ($last === null) {
            throw DraftException::because('No picks have been made yet.');
        }

        $last->delete();

        return $last;
    }

    /**
     * Assign draft slots after the coin flip. The named manager picks first in
     * every round; everyone else keeps their relative order behind them.
     *
     * @throws DraftException
     */
    public function setDraftOrder(Season $season, string $firstPickManagerName): void
    {
        $this->assertUnlocked($season);

        if ($season->draftPicks()->exists()) {
            throw DraftException::because('Picks have already been made, so the draft order is locked.');
        }

        $first = $this->resolveManager($season, $firstPickManagerName);

        $ordered = $season->managers()->get()
            ->sortBy(fn (Manager $manager): array => [$manager->id === $first->id ? 0 : 1, $manager->draft_slot])
            ->values();

        // Slots are unique per season, so swapping two managers head-on trips
        // the index. Park everyone on a negative slot first, then write the real
        // values.
        foreach ($ordered as $index => $manager) {
            $manager->update(['draft_slot' => -1 * ($index + 1)]);
        }

        foreach ($ordered as $index => $manager) {
            $manager->update(['draft_slot' => $index + 1]);
        }
    }

    /**
     * Resolve a manager by name: exact first, then a unique prefix, then a
     * unique substring.
     *
     * @throws DraftException
     */
    public function resolveManager(Season $season, string $name): Manager
    {
        $needle = Str::of($name)->trim()->lower()->squish()->value();
        $managers = $season->managers()->get();

        $tiers = [
            fn (Manager $m): bool => Str::lower($m->name) === $needle,
            fn (Manager $m): bool => str_starts_with(Str::lower($m->name), $needle),
            fn (Manager $m): bool => str_contains(Str::lower($m->name), $needle),
        ];

        foreach ($tiers as $tier) {
            $matches = $managers->filter($tier)->values();

            if ($matches->count() === 1) {
                return $matches->first();
            }

            if ($matches->count() > 1) {
                throw DraftException::because(sprintf(
                    '"%s" matches more than one manager: %s.',
                    $name,
                    $matches->map(fn (Manager $m): string => $m->name)->implode(', '),
                ));
            }
        }

        throw DraftException::because(sprintf(
            'No manager called "%s". This season has: %s.',
            $name,
            $managers->map(fn (Manager $m): string => $m->name)->implode(', ') ?: 'nobody yet',
        ));
    }

    /**
     * @throws DraftException
     */
    private function assertUnlocked(Season $season): void
    {
        if (! $this->isLocked($season)) {
            return;
        }

        throw DraftException::because(
            'The season has started, so the draft is locked and the rosters are final. '
            .'Delete the recorded episodes first if a pick really has to change.'
        );
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        // 23505 is Postgres' unique_violation; SQLite reports 23000.
        return in_array($exception->getCode(), ['23505', '23000'], true);
    }
}
