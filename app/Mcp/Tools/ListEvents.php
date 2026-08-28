<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Event;
use App\Services\CastawayResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class ListEvents extends LeagueTool
{
    protected string $name = 'list_events';

    protected string $description = 'List recorded events with their ids, so a wrong one can be found and deleted. '
        .'Filter by episode, by castaway, or both.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'episode' => $schema->integer()->description('Only events from this episode.'),
            'castaway' => $schema->string()->description('Only events for this castaway.'),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $season = $this->season($request);

        $query = Event::query()
            ->where('season_id', $season->id)
            ->with('castaway')
            ->orderBy('episode_number')
            ->orderBy('id');

        if ($request->get('episode') !== null) {
            $query->where('episode_number', (int) $request->get('episode'));
        }

        if ($request->get('castaway') !== null) {
            $castaway = app(CastawayResolver::class)->resolve($season, (string) $request->get('castaway'));
            $query->where('castaway_id', $castaway->id);
        }

        $events = $query->get();

        return $this->respond([
            'count' => $events->count(),
            'events' => $events->map(fn (Event $event): array => [
                'event_id' => $event->id,
                'episode' => $event->episode_number,
                'castaway' => $event->castaway->name,
                'type' => $event->type->value,
                'points_override' => $event->points,
                'note' => $event->note,
            ])->all(),
        ]);
    }
}
