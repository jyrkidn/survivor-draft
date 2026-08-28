<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EventType;
use App\Services\ScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class GetScoringRules extends LeagueTool
{
    protected string $name = 'get_scoring_rules';

    protected string $description = 'The scoring values in force for a season. Rules are data, not code — changing '
        .'one re-scores the whole season retroactively.';

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
        $rules = app(ScoringService::class)->rules($season);

        return $this->respond([
            'season' => $season->number,
            'rules' => array_map(fn (EventType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'points' => $rules[$type->value],
            ], EventType::cases()),
        ]);
    }
}
