<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Season;

final readonly class Scoreboard
{
    /**
     * @param  array<int, ManagerStanding>  $standings  Best first.
     * @param  array<int, CastawayScore>  $undrafted  What the unpicked castaways would have been worth.
     * @param  array<int, CastawayScore>  $stillIn
     */
    public function __construct(
        public Season $season,
        public int $episodesAired,
        public array $standings,
        public array $undrafted,
        public array $stillIn,
    ) {}

    /**
     * A plain-English summary of the lead, for the MCP client to read aloud.
     */
    public function leadSummary(): string
    {
        if (count($this->standings) < 2) {
            return 'Not enough managers to have a lead.';
        }

        [$first, $second] = $this->standings;
        $margin = $first->totalPoints - $second->totalPoints;

        if ($margin > 0) {
            return "{$first->manager->name} leads {$second->manager->name} by {$margin} point"
                .($margin === 1 ? '' : 's').'.';
        }

        if ($first->bestFinishRank !== $second->bestFinishRank) {
            return "{$first->manager->name} and {$second->manager->name} are tied on {$first->totalPoints} points; "
                ."{$first->manager->name} wins the tie-break on the higher-placing castaway.";
        }

        return "{$first->manager->name} and {$second->manager->name} are tied on {$first->totalPoints} points.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'season' => [
                'number' => $this->season->number,
                'title' => $this->season->title,
            ],
            'episodes_aired' => $this->episodesAired,
            'lead' => $this->leadSummary(),
            'standings' => array_map(fn (ManagerStanding $s): array => $s->toArray(), $this->standings),
            'still_in' => array_map(fn (CastawayScore $s): string => $s->castaway->name, $this->stillIn),
            'undrafted' => array_map(fn (CastawayScore $s): array => [
                'name' => $s->castaway->name,
                'points' => $s->totalPoints,
                'status' => $s->status,
            ], $this->undrafted),
        ];
    }
}
