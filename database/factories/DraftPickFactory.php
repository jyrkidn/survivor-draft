<?php

namespace Database\Factories;

use App\Models\Castaway;
use App\Models\DraftPick;
use App\Models\Manager;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DraftPick>
 */
class DraftPickFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'pick_number' => 1,
            'round' => 1,
            'manager_id' => Manager::factory(),
            'castaway_id' => Castaway::factory(),
        ];
    }
}
