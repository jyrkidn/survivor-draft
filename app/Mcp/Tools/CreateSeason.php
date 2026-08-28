<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Manager;
use App\Services\SeasonService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class CreateSeason extends LeagueTool
{
    protected string $name = 'create_season';

    protected string $description = 'Stand up a new season with its managers and a full set of default scoring '
        .'rules. Next season is one call, not a rebuild. Add the cast afterwards.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'number' => $schema->integer()->description('Season number, e.g. 52.')->required(),
            'title' => $schema->string()->description('Season title, e.g. "The Open Era".'),
            'premiere_date' => $schema->string()->description('Premiere date, YYYY-MM-DD.'),
            'roster_size' => $schema->integer()->description('How many castaways are in the cast. '
                .'Sets the draft length: 21 castaways and 2 managers gives 10 rounds and 20 picks.'),
            'managers' => $schema->array()->items($schema->string())
                ->description('Manager names, in initial draft-slot order. The coin flip can reorder them later '
                    .'with set_draft_order.'),
            'make_active' => $schema->boolean()->description('Make this the season every other tool defaults to. '
                .'Defaults to true.'),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $request->validate(['number' => ['required', 'integer', 'min:1']]);

        $managers = $request->get('managers');

        $season = app(SeasonService::class)->create(
            number: (int) $request->get('number'),
            title: $request->get('title') !== null ? (string) $request->get('title') : null,
            premiereDate: $request->get('premiere_date') !== null ? (string) $request->get('premiere_date') : null,
            rosterSize: $request->get('roster_size') !== null ? (int) $request->get('roster_size') : 0,
            managerNames: is_array($managers) ? array_map(fn (mixed $n): string => (string) $n, $managers) : [],
            makeActive: (bool) ($request->get('make_active') ?? true),
        );

        return $this->respond([
            'number' => $season->number,
            'title' => $season->title,
            'roster_size' => $season->roster_size,
            'is_active' => $season->is_active,
            'draft_rounds' => $season->draftRounds(),
            'total_picks' => $season->totalDraftPicks(),
            'managers' => $season->managers()->get()->map(fn (Manager $m): string => $m->name)->all(),
        ]);
    }
}
