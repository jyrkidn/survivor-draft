<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every kind of thing that can happen to a castaway.
 *
 * `Survived` is a rule, never a stored row: survival points are derived from the
 * elimination event and the number of aired episodes. See the ScoringService.
 */
enum EventType: string
{
    case Survived = 'survived';
    case TribeImmunity = 'tribe_immunity';
    case IndividualImmunity = 'individual_immunity';
    case AdvantageFound = 'advantage_found';
    case IdolFound = 'idol_found';
    case IdolPlayed = 'idol_played';
    case MadeMerge = 'made_merge';
    case FireWin = 'fire_win';
    case Winner = 'winner';
    case RunnerUp = 'runner_up';
    case Third = 'third';
    case Fourth = 'fourth';
    case VotedOut = 'voted_out';
    case Quit = 'quit';
    case Medevac = 'medevac';
    case LostFiremaking = 'lost_firemaking';

    /**
     * Types that take a castaway out of the game. The earliest one determines
     * the exit episode.
     *
     * @return array<int, self>
     */
    public static function eliminations(): array
    {
        return [self::VotedOut, self::Quit, self::Medevac, self::LostFiremaking];
    }

    /**
     * Types that record a final placing, best first.
     *
     * @return array<int, self>
     */
    public static function placements(): array
    {
        return [self::Winner, self::RunnerUp, self::Third, self::Fourth];
    }

    /**
     * Types `record_episode` accepts as name arrays, in the order it applies them.
     *
     * @return array<int, self>
     */
    public static function recordableInBulk(): array
    {
        return [
            self::VotedOut,
            self::Quit,
            self::Medevac,
            self::LostFiremaking,
            self::IndividualImmunity,
            self::TribeImmunity,
            self::IdolFound,
            self::IdolPlayed,
            self::AdvantageFound,
            self::FireWin,
        ];
    }

    /**
     * Types that may be stored as an event row. Everything except `survived`.
     *
     * @return array<int, self>
     */
    public static function storable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type !== self::Survived,
        ));
    }

    public function isElimination(): bool
    {
        return in_array($this, self::eliminations(), true);
    }

    public function isPlacement(): bool
    {
        return in_array($this, self::placements(), true);
    }

    /**
     * How good a placement is, best first. Null for non-placement types.
     */
    public function placementRank(): ?int
    {
        $rank = array_search($this, self::placements(), true);

        return $rank === false ? null : $rank + 1;
    }

    /**
     * The points this type is worth out of the box, used when seeding a season's
     * scoring rules. Rules live in the database; this is only the starting point.
     */
    public function defaultPoints(): int
    {
        return match ($this) {
            self::Survived, self::TribeImmunity => 1,
            self::IndividualImmunity, self::AdvantageFound => 2,
            self::IdolFound, self::FireWin => 3,
            self::IdolPlayed, self::MadeMerge, self::Fourth => 5,
            self::Third => 10,
            self::RunnerUp => 15,
            self::Winner => 25,
            self::VotedOut, self::Quit, self::Medevac, self::LostFiremaking => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Survived => 'Survived an episode',
            self::TribeImmunity => 'Won tribe immunity',
            self::IndividualImmunity => 'Won individual immunity',
            self::AdvantageFound => 'Found an advantage',
            self::IdolFound => 'Found a hidden immunity idol',
            self::IdolPlayed => 'Played an idol that voided votes',
            self::MadeMerge => 'Made the merge',
            self::FireWin => 'Won fire-making',
            self::Winner => 'Sole Survivor',
            self::RunnerUp => 'Runner-up',
            self::Third => 'Third place',
            self::Fourth => 'Fourth place',
            self::VotedOut => 'Voted out',
            self::Quit => 'Quit',
            self::Medevac => 'Medically evacuated',
            self::LostFiremaking => 'Lost fire-making',
        };
    }
}
