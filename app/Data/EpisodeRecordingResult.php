<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Episode;
use App\Models\Event;

/**
 * What `record_episode` did, plus everything it wants to say about it.
 *
 * Warnings are deliberately not failures: an award to someone already out is far
 * more likely to be a mis-numbered episode than a wrong name, so the entry is
 * kept and flagged rather than rejected mid-episode.
 */
final readonly class EpisodeRecordingResult
{
    /**
     * @param  array<int, Event>  $events
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public Episode $episode,
        public array $events,
        public array $warnings,
        public Scoreboard $scoreboard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'episode' => [
                'number' => $this->episode->number,
                'title' => $this->episode->title,
                'air_date' => $this->episode->air_date?->toDateString(),
                'notes' => $this->episode->notes,
            ],
            'recorded' => array_map(fn (Event $event): array => [
                'event_id' => $event->id,
                'castaway' => $event->castaway->name,
                'type' => $event->type->value,
            ], $this->events),
            'warnings' => $this->warnings,
            'standings' => array_map(
                fn (ManagerStanding $standing): array => [
                    'manager' => $standing->manager->name,
                    'points' => $standing->totalPoints,
                ],
                $this->scoreboard->standings,
            ),
            'lead' => $this->scoreboard->leadSummary(),
            'still_in' => array_map(
                fn (CastawayScore $score): string => $score->castaway->name,
                $this->scoreboard->stillIn,
            ),
        ];
    }
}
