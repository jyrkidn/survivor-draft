<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Event;
use App\Services\ScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class DeleteEvent extends LeagueTool
{
    protected string $name = 'delete_event';

    protected string $description = 'Delete one event by id. Everything downstream re-scores on its own, because '
        .'no total is stored anywhere. Use list_events to find the id.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id, from list_events.')->required(),
        ];
    }

    protected function run(Request $request): Response|ResponseFactory
    {
        $request->validate(['event_id' => ['required', 'integer']]);

        $event = Event::query()->with(['castaway', 'season'])->find((int) $request->get('event_id'));

        if ($event === null) {
            return Response::error("There is no event with id {$request->get('event_id')}.");
        }

        $castaway = $event->castaway;
        $description = "{$castaway->name} — {$event->type->label()} in episode {$event->episode_number}";

        $event->delete();

        $score = app(ScoringService::class)->scoreCastaway($castaway);

        return $this->respond([
            'deleted' => $description,
            'castaway' => $castaway->name,
            'castaway_points_now' => $score->totalPoints,
            'castaway_status' => $score->status,
        ]);
    }
}
