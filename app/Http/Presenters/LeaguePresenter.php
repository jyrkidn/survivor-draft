<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Data\CastawayScore;
use App\Models\Castaway;
use App\Models\DraftPick;
use App\Models\Manager;
use Illuminate\Support\Str;

/**
 * Shapes the domain objects into the props the three Inertia screens read.
 *
 * Managers are named, never reduced to a colour. Coral and gold always sit next
 * to the manager's own name, so the screens stay readable without colour
 * vision — and on the draft board, column position carries it as well. The one
 * place a name will not fit is the 20px badge on a cast tile, which gets the
 * manager's initial instead.
 */
final class LeaguePresenter
{
    /**
     * @return array{id: int, name: string, slot: int}
     */
    public static function manager(Manager $manager): array
    {
        return [
            'id' => $manager->id,
            'name' => $manager->name,
            'slot' => $manager->draft_slot,
        ];
    }

    /**
     * Shortest initials that still tell the managers apart, so "Sam" and
     * "Sarah" do not both come out as S.
     *
     * @param  iterable<Manager>  $managers
     * @return array<int, string> Manager id => initial.
     */
    public static function initials(iterable $managers): array
    {
        $managers = collect($managers);
        $longest = (int) $managers->max(fn (Manager $manager): int => Str::length($manager->name));

        for ($length = 1; $length < max(1, $longest); $length++) {
            $initials = $managers
                ->mapWithKeys(fn (Manager $manager): array => [
                    $manager->id => Str::upper(Str::substr($manager->name, 0, $length)),
                ])
                ->all();

            if (count(array_unique($initials)) === count($initials)) {
                return $initials;
            }
        }

        return $managers
            ->mapWithKeys(fn (Manager $manager): array => [
                $manager->id => Str::upper($manager->name),
            ])
            ->all();
    }

    /**
     * A castaway as the cast grid shows them.
     *
     * @param  array<int, string>  $initials
     * @return array<string, mixed>
     */
    public static function card(CastawayScore $score, array $initials = []): array
    {
        $castaway = $score->castaway;
        $owner = $score->owner;

        return [
            'id' => $castaway->id,
            'name' => $castaway->name,
            'age' => $castaway->age,
            'occupation' => $castaway->occupation,
            'tribe' => $castaway->tribe,
            'photo_url' => $castaway->photoUrl(),
            'status' => $score->status,
            'exit_episode' => $score->exitEpisode,
            'points' => $score->totalPoints,
            'owner_slot' => $owner?->draft_slot,
            'owner_name' => $owner?->name,
            'owner_initial' => $owner === null ? null : ($initials[$owner->id] ?? Str::upper(Str::substr($owner->name, 0, 1))),
        ];
    }

    /**
     * An undrafted castaway in the draft's available grid. No score is shown
     * here, so this stays cheap enough to run before any episode has aired.
     *
     * @return array<string, mixed>
     */
    public static function availableCastaway(Castaway $castaway): array
    {
        return [
            'id' => $castaway->id,
            'name' => $castaway->name,
            'age' => $castaway->age,
            'occupation' => $castaway->occupation,
            'tribe' => $castaway->tribe,
            'photo_url' => $castaway->photoUrl(),
        ];
    }

    /**
     * One completed cell on the draft board.
     *
     * @return array<string, mixed>
     */
    public static function pick(DraftPick $pick): array
    {
        return [
            'pick_number' => $pick->pick_number,
            'round' => $pick->round,
            'manager_slot' => $pick->manager->draft_slot,
            'manager_name' => $pick->manager->name,
            'castaway' => [
                'id' => $pick->castaway->id,
                'name' => $pick->castaway->name,
                'tribe' => $pick->castaway->tribe,
                'age' => $pick->castaway->age,
            ],
        ];
    }

    /**
     * A roster row on the scoreboard.
     *
     * @return array<string, mixed>
     */
    public static function rosterRow(CastawayScore $score): array
    {
        return [
            'id' => $score->castaway->id,
            'name' => $score->castaway->name,
            'points' => $score->totalPoints,
            'status' => $score->status,
            'exit_episode' => $score->exitEpisode,
        ];
    }
}
