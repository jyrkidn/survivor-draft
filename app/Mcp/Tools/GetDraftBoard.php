<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Castaway;
use App\Models\DraftPick;
use App\Services\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class GetDraftBoard extends LeagueTool
{
    protected string $name = 'get_draft_board';

    protected string $description = 'The draft: the full pick order, the picks made so far, who is on the '
        .'clock, and which castaways are still available.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['season' => $this->seasonProperty($schema)];
    }

    protected function run(Request $request): ResponseFactory
    {
        $season = $this->season($request);
        $draft = app(DraftService::class);

        $onTheClock = $draft->onTheClock($season);

        return $this->respond([
            'season' => $season->number,
            'rounds' => $season->draftRounds(),
            'total_picks' => $season->totalDraftPicks(),
            'picks_made' => $season->draftPicks()->with(['manager', 'castaway'])->get()
                ->map(fn (DraftPick $pick): array => [
                    'pick_number' => $pick->pick_number,
                    'round' => $pick->round,
                    'manager' => $pick->manager->name,
                    'castaway' => $pick->castaway->name,
                ])->all(),
            'on_the_clock' => $onTheClock?->name,
            'next_pick_number' => $draft->isComplete($season) ? null : $draft->nextPickNumber($season),
            'complete' => $draft->isComplete($season),
            // Locked once the season starts; the rosters are final from then on.
            'locked' => $draft->isLocked($season),
            'pick_order' => array_map(fn (array $slot): array => [
                'pick_number' => $slot['pick_number'],
                'round' => $slot['round'],
                'manager' => $slot['manager']->name,
            ], $draft->pickOrder($season)),
            'available' => $draft->availableCastaways($season)
                ->map(fn (Castaway $castaway): string => $castaway->name)->all(),
        ]);
    }
}
