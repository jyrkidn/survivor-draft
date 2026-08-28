<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Castaway;
use App\Models\Event;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'episode_number' => fake()->numberBetween(1, 14),
            'castaway_id' => Castaway::factory(),
            'type' => EventType::TribeImmunity,
            'points' => null,
            'note' => null,
        ];
    }

    public function ofType(EventType $type): static
    {
        return $this->state(fn (array $attributes): array => ['type' => $type]);
    }

    public function inEpisode(int $number): static
    {
        return $this->state(fn (array $attributes): array => ['episode_number' => $number]);
    }
}
