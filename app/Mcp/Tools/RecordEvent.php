<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EventType;
use App\Models\Event;
use App\Services\CastawayResolver;
use App\Services\ScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class RecordEvent extends LeagueTool
{
    protected string $name = 'record_event';

    protected string $description = 'Record one single event, for corrections. Prefer record_episode for anything '
        .'that happened in an episode you are entering fresh — this one adds to what is already there rather than '
        .'replacing it.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'castaway' => $schema->string()->description('Who it happened to.')->required(),
            'type' => $schema->string()
                ->enum(array_map(fn (EventType $t): string => $t->value, EventType::storable()))
                ->description('What happened. "survived" is not recordable — it is derived.')
                ->required(),
            'episode' => $schema->integer()->description('Episode number. Defaults to the latest aired episode.'),
            'points' => $schema->integer()->description('Override the season rule for this one event only.'),
            'note' => $schema->string()->description('Why this event was recorded.'),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'castaway' => ['required', 'string'],
            'type' => ['required', 'string'],
        ]);

        $season = $this->season($request);

        $type = EventType::tryFrom((string) $request->get('type'));

        if ($type === null || $type === EventType::Survived) {
            return Response::error(sprintf(
                '"%s" is not a recordable event type. Use one of: %s.',
                $request->get('type'),
                implode(', ', array_map(fn (EventType $t): string => $t->value, EventType::storable())),
            ));
        }

        $castaway = app(CastawayResolver::class)->resolve($season, (string) $request->get('castaway'));

        $episodeNumber = $request->get('episode') !== null
            ? (int) $request->get('episode')
            : (int) $season->episodes()->max('number');

        if ($episodeNumber < 1) {
            return Response::error('No episodes have aired yet, so pass an episode number explicitly.');
        }

        $event = Event::query()->create([
            'season_id' => $season->id,
            'episode_number' => $episodeNumber,
            'castaway_id' => $castaway->id,
            'type' => $type,
            'points' => $request->get('points') !== null ? (int) $request->get('points') : null,
            'note' => $request->get('note') !== null ? (string) $request->get('note') : null,
        ]);

        $score = app(ScoringService::class)->scoreCastaway($castaway);

        return $this->respond([
            'event_id' => $event->id,
            'castaway' => $castaway->name,
            'type' => $type->value,
            'episode' => $episodeNumber,
            'castaway_points_now' => $score->totalPoints,
            'castaway_status' => $score->status,
        ]);
    }
}
