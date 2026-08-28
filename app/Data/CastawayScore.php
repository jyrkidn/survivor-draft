<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\EventType;
use App\Models\Castaway;
use App\Models\Manager;

/**
 * Everything derivable about one castaway. Nothing here is stored; it is all
 * recomputed from the event log and the aired-episode count on every read.
 */
final readonly class CastawayScore
{
    /**
     * @param  array<int, array{episode: int, type: string, label: string, points: int, note: string|null, event_id: int}>  $breakdown
     */
    public function __construct(
        public Castaway $castaway,
        public ?Manager $owner,
        public string $status,
        public ?int $exitEpisode,
        public ?EventType $placement,
        public int $survivedEpisodes,
        public int $survivalPoints,
        public int $eventPoints,
        public int $totalPoints,
        public int $finishRank,
        public array $breakdown,
    ) {}

    public function isStillIn(): bool
    {
        return $this->status === 'in';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->castaway->id,
            'name' => $this->castaway->name,
            'nickname' => $this->castaway->nickname,
            'tribe' => $this->castaway->tribe,
            'status' => $this->status,
            'exit_episode' => $this->exitEpisode,
            'placement' => $this->placement?->value,
            'owner' => $this->owner?->name,
            'survived_episodes' => $this->survivedEpisodes,
            'survival_points' => $this->survivalPoints,
            'event_points' => $this->eventPoints,
            'points' => $this->totalPoints,
            'breakdown' => $this->breakdown,
        ];
    }
}
