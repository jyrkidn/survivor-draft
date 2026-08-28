<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Manager;
use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class ListSeasons extends LeagueTool
{
    protected string $name = 'list_seasons';

    protected string $description = 'Every season on record, newest first, and which one is active.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request): ResponseFactory
    {
        $seasons = app(SeasonService::class)->all();

        return $this->respond([
            'count' => $seasons->count(),
            'seasons' => $seasons->map(fn (Season $season): array => [
                'number' => $season->number,
                'title' => $season->title,
                'premiere_date' => $season->premiere_date?->toDateString(),
                'roster_size' => $season->roster_size,
                'is_active' => $season->is_active,
                'castaways' => $season->castaways_count,
                'episodes_aired' => $season->episodes_count,
                'picks_made' => $season->draft_picks_count,
                'managers' => $season->managers()->get()->map(fn (Manager $m): string => $m->name)->all(),
            ])->all(),
        ]);
    }
}
