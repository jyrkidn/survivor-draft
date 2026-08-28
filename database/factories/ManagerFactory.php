<?php

namespace Database\Factories;

use App\Models\Manager;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manager>
 */
class ManagerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'name' => fake()->unique()->firstName(),
            'draft_slot' => 1,
        ];
    }

    public function inSlot(int $slot): static
    {
        return $this->state(fn (array $attributes): array => ['draft_slot' => $slot]);
    }
}
