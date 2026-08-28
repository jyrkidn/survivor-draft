<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\SeasonService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class SetActiveSeason extends LeagueTool
{
    protected string $name = 'set_active_season';

    protected string $description = 'Choose the season every other tool defaults to.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'season' => $schema->integer()->description('The season number to make active.')->required(),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $request->validate(['season' => ['required', 'integer']]);

        $seasons = app(SeasonService::class);
        $season = $seasons->setActive($seasons->resolve((int) $request->get('season')));

        return $this->respond([
            'active_season' => $season->number,
            'title' => $season->title,
        ]);
    }
}
