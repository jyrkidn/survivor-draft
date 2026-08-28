<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Manager;
use App\Services\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class SetDraftOrder extends LeagueTool
{
    protected string $name = 'set_draft_order';

    protected string $description = 'Set the draft order after the coin flip. Name the manager taking the first '
        .'pick. The order is straight alternating, so that manager picks first in every round and nobody ever picks '
        .'twice in a row. Refuses once any pick has been made.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'first_pick' => $schema->string()->description('The manager taking pick 1.')->required(),
            'season' => $this->seasonProperty($schema),
        ];
    }

    protected function run(Request $request): ResponseFactory
    {
        $request->validate(['first_pick' => ['required', 'string']]);

        $season = $this->season($request);
        $draft = app(DraftService::class);

        $draft->setDraftOrder($season, (string) $request->get('first_pick'));

        return $this->respond([
            'order' => $season->managers()->get()
                ->map(fn (Manager $manager): array => [
                    'draft_slot' => $manager->draft_slot,
                    'manager' => $manager->name,
                ])->all(),
            'on_the_clock' => $draft->onTheClock($season)?->name,
        ]);
    }
}
