<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\EventType;

/**
 * One call's worth of "here is what happened this week", as names rather than ids.
 */
final readonly class EpisodeRecording
{
    /**
     * @param  array<string, array<int, string>>  $names  Event type value => castaway or tribe names.
     */
    public function __construct(
        public int $episode,
        public ?string $title = null,
        public ?string $airDate = null,
        public ?string $notes = null,
        public bool $merge = false,
        public array $names = [],
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $names = [];

        foreach (EventType::recordableInBulk() as $type) {
            $given = $input[$type->value] ?? [];

            if (is_string($given)) {
                $given = [$given];
            }

            if (! is_array($given)) {
                continue;
            }

            $cleaned = array_values(array_filter(
                array_map(fn (mixed $name): string => trim((string) $name), $given),
                fn (string $name): bool => $name !== '',
            ));

            if ($cleaned !== []) {
                $names[$type->value] = $cleaned;
            }
        }

        return new self(
            episode: (int) $input['episode'],
            title: isset($input['title']) ? (string) $input['title'] : null,
            airDate: isset($input['air_date']) ? (string) $input['air_date'] : null,
            notes: isset($input['notes']) ? (string) $input['notes'] : null,
            merge: (bool) ($input['merge'] ?? false),
            names: $names,
        );
    }

    /**
     * @return array<int, string>
     */
    public function namesFor(EventType $type): array
    {
        return $this->names[$type->value] ?? [];
    }
}
