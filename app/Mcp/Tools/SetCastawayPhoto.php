<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\CastawayResolver;
use App\Services\PhotoIngestor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class SetCastawayPhoto extends LeagueTool
{
    protected string $name = 'set_castaway_photo';

    protected string $description = 'Fetch one cast photo from an https URL and store it locally. If the download '
        .'is blocked the URL is remembered anyway and the board hotlinks it, so this reports a warning rather than '
        .'failing. For a whole cast, use set_castaway_photos instead.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'castaway' => $schema->string()->description('Who the photo is of.')->required(),
            'url' => $schema->string()->description('An https URL to the image.')->required(),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $request->validate([
            'castaway' => ['required', 'string'],
            'url' => ['required', 'string'],
        ]);

        $season = $this->season($request);
        $castaway = app(CastawayResolver::class)->resolve($season, (string) $request->get('castaway'));

        $result = app(PhotoIngestor::class)->ingest($castaway, (string) $request->get('url'));

        return $this->respond($result);
    }
}
