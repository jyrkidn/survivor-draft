<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\CastService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class AddCastaways extends LeagueTool
{
    protected string $name = 'add_castaways';

    protected string $description = <<<'TEXT'
    Add castaways to a season, or correct ones already there. Give it the whole cast in one
    call rather than one call each.

    Matching is on the name, so calling it again for a castaway who already exists updates
    them instead of creating a duplicate — which is how you fix a typo in an age or a tribe.
    A second call carrying only a name leaves the rest of their details alone.

    Set the tribe here if you know it; it can also be done later with set_tribes, and it is
    what lets tribe immunity be recorded by tribe name.
    TEXT;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'castaways' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()
                        ->description('Full name, as it should read on the board.')
                        ->required(),
                    'nickname' => $schema->string()
                        ->description('What people actually call them. Helps name matching during an episode.'),
                    'age' => $schema->integer()->description('Age at filming.'),
                    'occupation' => $schema->string(),
                    'hometown' => $schema->string(),
                    'tribe' => $schema->string()->description('Starting tribe.'),
                ])
            )->description('One entry per castaway.')->required(),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): Response|ResponseFactory
    {
        $season = $this->season($request);
        $castaways = $request->get('castaways');

        if (! is_array($castaways) || $castaways === []) {
            return Response::error('Pass at least one castaway, each with at least a name.');
        }

        /** @var array<int, array<string, mixed>> $entries */
        $entries = array_values(array_filter(
            $castaways,
            fn (mixed $entry): bool => is_array($entry),
        ));

        if ($entries === []) {
            return Response::error('Every entry must be an object with at least a name.');
        }

        return $this->respond([
            'season' => $season->number,
            ...app(CastService::class)->register($season, $entries),
        ]);
    }
}
