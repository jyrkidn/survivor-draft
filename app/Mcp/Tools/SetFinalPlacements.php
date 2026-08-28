<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Event;
use App\Services\EpisodeRecorder;
use App\Services\ScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class SetFinalPlacements extends LeagueTool
{
    protected string $name = 'set_final_placements';

    protected string $description = 'Record the finale bonuses. Replaces any placements already recorded, so it is '
        .'safe to call again with a correction.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'winner' => $schema->string()->description('The Sole Survivor.')->required(),
            'runner_up' => $schema->string()->description('Second place.')->required(),
            'third' => $schema->string()->description('Third place.')->required(),
            'fourth' => $schema->string()->description('Fourth place, if the season had a final four.'),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $request->validate([
            'winner' => ['required', 'string'],
            'runner_up' => ['required', 'string'],
            'third' => ['required', 'string'],
        ]);

        $season = $this->season($request);

        $events = app(EpisodeRecorder::class)->setFinalPlacements($season, [
            'winner' => (string) $request->get('winner'),
            'runner_up' => (string) $request->get('runner_up'),
            'third' => (string) $request->get('third'),
            'fourth' => $request->get('fourth') !== null ? (string) $request->get('fourth') : null,
        ]);

        return $this->respond([
            'placements' => array_map(fn (Event $event): array => [
                'placement' => $event->type->value,
                'castaway' => $event->castaway->name,
            ], $events),
            'scoreboard' => app(ScoringService::class)->scoreboard($season)->toArray(),
        ]);
    }
}
