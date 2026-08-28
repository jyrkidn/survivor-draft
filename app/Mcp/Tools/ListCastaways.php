<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\CastawayScore;
use App\Services\ScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class ListCastaways extends LeagueTool
{
    protected string $name = 'list_castaways';

    protected string $description = 'The cast, with each castaway\'s status, tribe, which manager owns them, and '
        .'how many points they have scored.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['in', 'out', 'all'])
                ->description('Filter by whether they are still in the game. Defaults to all.'),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $season = $this->season($request);
        $status = $request->get('status') ?? 'all';

        $scores = app(ScoringService::class)->scoreCastaways($season);

        if ($status !== 'all') {
            $scores = array_filter($scores, fn (CastawayScore $score): bool => $score->status === $status);
        }

        $rows = array_map(fn (CastawayScore $score): array => [
            'name' => $score->castaway->name,
            'nickname' => $score->castaway->nickname,
            'age' => $score->castaway->age,
            'occupation' => $score->castaway->occupation,
            'hometown' => $score->castaway->hometown,
            'tribe' => $score->castaway->tribe,
            'status' => $score->status,
            'exit_episode' => $score->exitEpisode,
            'owner' => $score->owner?->name,
            'points' => $score->totalPoints,
            'has_photo' => $score->castaway->photoUrl() !== null,
        ], array_values($scores));

        usort($rows, fn (array $a, array $b): int => $b['points'] <=> $a['points']);

        return $this->respond(['count' => count($rows), 'castaways' => $rows]);
    }
}
