<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AmbiguousCastawayException;
use App\Exceptions\CastawayNotFoundException;
use App\Models\Castaway;
use App\Models\Season;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns whatever a human said into one castaway.
 *
 * During an episode nobody types full names. "Jelly", "Kilby", "Thien An" and
 * "Rob" all have to land, so matching walks progressively looser tiers and stops
 * at the first one that hits. Ambiguity is reported with the candidates named,
 * so the caller can ask rather than guess.
 */
final class CastawayResolver
{
    /**
     * @throws CastawayNotFoundException
     * @throws AmbiguousCastawayException
     */
    public function resolve(Season $season, string $name): Castaway
    {
        $castaways = Castaway::query()
            ->where('season_id', $season->id)
            ->orderBy('name')
            ->get();

        return $this->resolveIn($castaways, $name);
    }

    /**
     * Resolve against an already-loaded cast. Use this when resolving many names
     * at once, as `record_episode` does.
     *
     * @param  Collection<int, Castaway>  $castaways
     *
     * @throws CastawayNotFoundException
     * @throws AmbiguousCastawayException
     */
    public function resolveIn(Collection $castaways, string $name): Castaway
    {
        $needle = $this->normalise($name);

        if ($needle === '') {
            throw CastawayNotFoundException::forName($name);
        }

        foreach ($this->tiers() as $tier) {
            $matches = $castaways->filter(fn (Castaway $castaway): bool => $tier($castaway, $needle))->values();

            if ($matches->count() === 1) {
                return $matches->first();
            }

            if ($matches->count() > 1) {
                throw new AmbiguousCastawayException(
                    $name,
                    $matches->map(fn (Castaway $castaway): string => $castaway->name)->all(),
                );
            }
        }

        throw CastawayNotFoundException::forName($name);
    }

    /**
     * Resolve a name only if it is unambiguous, returning null instead of throwing.
     *
     * @param  Collection<int, Castaway>  $castaways
     */
    public function tryResolveIn(Collection $castaways, string $name): ?Castaway
    {
        try {
            return $this->resolveIn($castaways, $name);
        } catch (CastawayNotFoundException|AmbiguousCastawayException) {
            return null;
        }
    }

    /**
     * Progressively looser matchers. The first tier producing any match decides
     * the outcome, so an exact full name is never beaten by a stray substring.
     *
     * @return array<int, callable(Castaway, string): bool>
     */
    private function tiers(): array
    {
        return [
            fn (Castaway $c, string $needle): bool => $this->normalise($c->name) === $needle,
            fn (Castaway $c, string $needle): bool => $c->nickname !== null
                && $this->normalise($c->nickname) === $needle,
            fn (Castaway $c, string $needle): bool => $this->firstName($c->name) === $needle,
            fn (Castaway $c, string $needle): bool => $this->lastName($c->name) === $needle,
            // Catches partial names the earlier tiers cannot, such as "Thien An"
            // for "Thien An Do".
            fn (Castaway $c, string $needle): bool => str_contains($this->normalise($c->name), $needle)
                || ($c->nickname !== null && str_contains($this->normalise($c->nickname), $needle)),
        ];
    }

    private function normalise(string $value): string
    {
        return Str::of($value)->trim()->lower()->squish()->value();
    }

    private function firstName(string $name): string
    {
        $parts = explode(' ', $this->normalise($name));

        return $parts[0];
    }

    private function lastName(string $name): string
    {
        $parts = explode(' ', $this->normalise($name));

        return (string) end($parts);
    }
}
