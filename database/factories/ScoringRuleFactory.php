<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\ScoringRule;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoringRule>
 */
class ScoringRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(EventType::cases());

        return [
            'season_id' => Season::factory(),
            'type' => $type,
            'points' => $type->defaultPoints(),
            'label' => $type->label(),
        ];
    }
}
