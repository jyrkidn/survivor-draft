<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class MakeDraftPick extends LeagueTool
{
    protected string $name = 'make_draft_pick';

    protected string $description = 'Record one draft pick. Validated against the alternating pick order: it '
        .'refuses if it is not that manager\'s turn, or if the castaway is already taken.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'manager' => $schema->string()->description('The manager making the pick.')->required(),
            'castaway' => $schema->string()->description('The castaway being picked.')->required(),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $request->validate([
            'manager' => ['required', 'string'],
            'castaway' => ['required', 'string'],
        ]);

        $season = $this->season($request);
        $draft = app(DraftService::class);

        $pick = $draft->makePick($season, (string) $request->get('manager'), (string) $request->get('castaway'));
        $pick->load(['manager', 'castaway']);

        return $this->respond([
            'pick_number' => $pick->pick_number,
            'round' => $pick->round,
            'manager' => $pick->manager->name,
            'castaway' => $pick->castaway->name,
            'on_the_clock' => $draft->onTheClock($season)?->name,
            'complete' => $draft->isComplete($season),
            'available_remaining' => $draft->availableCastaways($season)->count(),
        ]);
    }
}
