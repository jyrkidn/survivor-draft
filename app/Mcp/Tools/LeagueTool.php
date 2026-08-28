<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Exceptions\LeagueException;
use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Shared plumbing for every tool in this server.
 *
 * The important bit is the error handling: a domain failure comes back as a
 * *result* with `isError: true` and a plain-English message, not a JSON-RPC
 * error. The model can read the former and try again; the latter just ends the
 * conversation.
 */
abstract class LeagueTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            return $this->run($request);
        } catch (LeagueException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    abstract protected function run(Request $request): Response|ResponseFactory;

    /**
     * The season this call is about: the one named, or the active one.
     */
    protected function season(Request $request): Season
    {
        $number = $request->get('season');

        return app(SeasonService::class)->resolve(
            $number === null || $number === '' ? null : (int) $number,
        );
    }

    protected function seasonProperty(JsonSchema $schema): Type
    {
        return $schema->integer()
            ->description('Season number. Defaults to the active season, so leave it out unless asked otherwise.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function respond(array $payload): ResponseFactory
    {
        return Response::structured($payload === [] ? ['result' => 'nothing to report'] : $payload);
    }
}
