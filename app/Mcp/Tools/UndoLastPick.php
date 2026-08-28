<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;

final class UndoLastPick extends LeagueTool
{
    protected string $name = 'undo_last_pick';

    protected string $description = 'Remove the most recent draft pick and hand the clock back. Draft night is chaotic.';

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
        $draft = app(DraftService::class);

        $undone = $draft->undoLastPick($season);

        return $this->respond([
            'undone' => [
                'pick_number' => $undone->pick_number,
                'manager' => $undone->manager->name,
                'castaway' => $undone->castaway->name,
            ],
            'on_the_clock' => $draft->onTheClock($season)?->name,
            'next_pick_number' => $draft->nextPickNumber($season),
        ]);
    }
}
