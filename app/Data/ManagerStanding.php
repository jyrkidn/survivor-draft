<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Manager;

final readonly class ManagerStanding
{
    /**
     * @param  array<int, CastawayScore>  $roster
     */
    public function __construct(
        public Manager $manager,
        public array $roster,
        public int $totalPoints,
        public int $bestFinishRank,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'manager' => $this->manager->name,
            'draft_slot' => $this->manager->draft_slot,
            'points' => $this->totalPoints,
            'roster' => array_map(fn (CastawayScore $s): array => $s->toArray(), $this->roster),
        ];
    }
}
