<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\ScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class GetScoreboard extends LeagueTool
{
    protected string $name = 'get_scoreboard';

    protected string $description = 'The standings: both managers with their totals, every drafted castaway with '
        .'its points and status, how many episodes have aired, and who is leading.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['season' => $this->seasonProperty($schema)];
    }

    protected function run(Request $request): ResponseFactory
    {
        return $this->respond(
            app(ScoringService::class)->scoreboard($this->season($request))->toArray(),
        );
    }
}
