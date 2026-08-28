<?php

namespace Database\Seeders;

use App\Models\Manager;
use App\Models\Season;
use App\Services\ScoringService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Season 51, its two managers and the full scoring table.
 *
 * The cast is deliberately not seeded — add the 21 castaways through the
 * Filament panel or an MCP call once the line-up is announced. Everything else
 * here is safe to re-run: it updates rather than duplicates.
 */
class SeasonFiftyOneSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Rename these in the panel once the league is set up. The coin flip decides
     * the real order later, via set_draft_order.
     *
     * @var array<int, string>
     */
    private const MANAGERS = ['Manager 1', 'Manager 2'];

    public function run(): void
    {
        $season = Season::query()->updateOrCreate(
            ['number' => 51],
            [
                'title' => 'The Open Era',
                'premiere_date' => '2026-09-23',
                'roster_size' => 21,
                'is_active' => true,
            ],
        );

        foreach (self::MANAGERS as $index => $name) {
            Manager::query()->updateOrCreate(
                ['season_id' => $season->id, 'draft_slot' => $index + 1],
                ['name' => $name],
            );
        }

        app(ScoringService::class)->seedDefaultRules($season);

        $this->command->info(sprintf(
            'Season %d seeded: %d managers, %d draft rounds, %d picks. Cast not seeded — add 21 castaways.',
            $season->number,
            count(self::MANAGERS),
            $season->draftRounds(),
            $season->totalDraftPicks(),
        ));
    }
}
