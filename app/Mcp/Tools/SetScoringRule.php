<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EventType;
use App\Models\ScoringRule;
use App\Services\ScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class SetScoringRule extends LeagueTool
{
    protected string $name = 'set_scoring_rule';

    protected string $description = 'Change what one event type is worth. This re-scores the entire season '
        .'immediately and retroactively, because no total is stored anywhere.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(array_map(fn (EventType $t): string => $t->value, EventType::cases()))
                ->description('The event type to re-price.')->required(),
            'points' => $schema->integer()->description('What it is now worth.')->required(),
            'label' => $schema->string()->description('An optional human-readable name for the rule.'),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'type' => ['required', 'string'],
            'points' => ['required', 'integer'],
        ]);

        $season = $this->season($request);
        $type = EventType::tryFrom((string) $request->get('type'));

        if ($type === null) {
            return Response::error(sprintf(
                '"%s" is not an event type. Use one of: %s.',
                $request->get('type'),
                implode(', ', array_map(fn (EventType $t): string => $t->value, EventType::cases())),
            ));
        }

        $points = (int) $request->get('points');

        ScoringRule::query()->updateOrCreate(
            ['season_id' => $season->id, 'type' => $type],
            [
                'points' => $points,
                'label' => $request->get('label') !== null ? (string) $request->get('label') : $type->label(),
            ],
        );

        return $this->respond([
            'type' => $type->value,
            'points' => $points,
            'scoreboard' => app(ScoringService::class)->scoreboard($season)->toArray(),
        ]);
    }
}
