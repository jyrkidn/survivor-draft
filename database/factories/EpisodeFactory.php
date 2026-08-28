<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'number' => fake()->unique()->numberBetween(1, 20),
            'title' => fake()->sentence(3),
            'air_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'notes' => null,
        ];
    }
}
