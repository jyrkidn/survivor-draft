<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the MCP endpoint.
 *
 * The token is also accepted as a path segment (`/mcp/<token>`), because
 * Claude's custom connector UI takes a URL far more easily than it takes a
 * header. Both forms are compared in constant time, and a server with no
 * configured token refuses everything rather than opening up.
 */
final class AuthenticateMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.mcp.token');

        if (! is_string($expected) || $expected === '') {
            return $this->unauthorised('The MCP server has no token configured.');
        }

        $presented = $this->presentedToken($request);

        if ($presented === null || ! hash_equals($expected, $presented)) {
            return $this->unauthorised('Invalid or missing MCP token.');
        }

        return $next($request);
    }

    private function presentedToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $fromPath = $request->route('token');

        return is_string($fromPath) && $fromPath !== '' ? $fromPath : null;
    }

    private function unauthorised(string $message): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32001, 'message' => $message],
            'id' => null,
        ], 401);
    }
}
