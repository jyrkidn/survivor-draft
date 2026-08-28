<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\CastawayResolver;
use App\Services\PhotoIngestor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class SetCastawayPhotos extends LeagueTool
{
    protected string $name = 'set_castaway_photos';

    protected string $description = 'Fetch the whole cast\'s photos in one call. Returns a row per castaway, so you '
        .'can see exactly which ones did not land rather than just that some failed.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'photos' => $schema->array()->items(
                $schema->object([
                    'castaway' => $schema->string()->description('Who the photo is of.')->required(),
                    'url' => $schema->string()->description('An https URL to the image.')->required(),
                ])
            )->description('One entry per castaway.')->required(),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): Response|ResponseFactory
    {
        $season = $this->season($request);
        $photos = $request->get('photos');

        if (! is_array($photos) || $photos === []) {
            return Response::error('Pass at least one {castaway, url} entry.');
        }

        /** @var array<int, array{castaway: string, url: string}> $entries */
        $entries = array_values(array_map(fn (mixed $photo): array => [
            'castaway' => is_array($photo) ? (string) ($photo['castaway'] ?? '') : '',
            'url' => is_array($photo) ? (string) ($photo['url'] ?? '') : '',
        ], $photos));

        $results = app(PhotoIngestor::class)->ingestMany($season, $entries, app(CastawayResolver::class));

        $failed = array_values(array_filter($results, fn (array $row): bool => ! $row['stored']));

        return $this->respond([
            'stored' => count($results) - count($failed),
            'failed' => count($failed),
            'results' => $results,
        ]);
    }
}
