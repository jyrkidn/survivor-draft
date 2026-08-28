<?php

namespace Database\Factories;

use App\Models\Castaway;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Castaway>
 */
class CastawayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'name' => fake()->unique()->name(),
            'nickname' => null,
            'age' => fake()->numberBetween(19, 62),
            'occupation' => fake()->jobTitle(),
            'hometown' => fake()->city(),
            'tribe' => null,
        ];
    }

    public function onTribe(string $tribe): static
    {
        return $this->state(fn (array $attributes): array => ['tribe' => $tribe]);
    }
}
