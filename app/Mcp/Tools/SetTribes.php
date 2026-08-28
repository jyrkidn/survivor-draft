<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\CastawayResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class SetTribes extends LeagueTool
{
    protected string $name = 'set_tribes';

    protected string $description = 'Assign castaways to tribes, so tribe immunity can be recorded by tribe name '
        .'rather than by listing everyone. Call it again after a swap or a merge.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'assignments' => $schema->array()->items(
                $schema->object([
                    'castaway' => $schema->string()->description('The castaway.')->required(),
                    'tribe' => $schema->string()->description('Their tribe name.')->required(),
                ])
            )->description('One entry per castaway being assigned.')->required(),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): Response|ResponseFactory
    {
        $season = $this->season($request);
        $assignments = $request->get('assignments');

        if (! is_array($assignments) || $assignments === []) {
            return Response::error('Pass at least one {castaway, tribe} assignment.');
        }

        $resolver = app(CastawayResolver::class);
        $cast = $season->castaways()->orderBy('name')->get();

        $applied = [];
        $warnings = [];

        foreach ($assignments as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }

            $name = trim((string) ($assignment['castaway'] ?? ''));
            $tribe = trim((string) ($assignment['tribe'] ?? ''));

            $castaway = $resolver->tryResolveIn($cast, $name);

            if ($castaway === null) {
                $warnings[] = "Could not match \"{$name}\" to a castaway. Skipped.";

                continue;
            }

            $castaway->update(['tribe' => $tribe === '' ? null : $tribe]);
            $applied[$castaway->name] = $tribe;
        }

        return $this->respond([
            'assigned' => $applied,
            'warnings' => $warnings,
            'tribes' => $season->castaways()->whereNotNull('tribe')
                ->get()->groupBy('tribe')->map->count()->all(),
        ]);
    }
}
