<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Mcp\Servers\SurvivorDraftServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP
|--------------------------------------------------------------------------
|
| These routes are registered outside the `web` group, so they carry no
| session auth and no CSRF — the connector handshake fails in confusing ways
| if they do. Authentication is the bearer token instead.
|
| The token is also accepted as a path segment, because Claude's custom
| connector UI takes a URL far more readily than it takes a header.
|
*/

Mcp::web('mcp', SurvivorDraftServer::class)->middleware(AuthenticateMcp::class);

Mcp::web('mcp/{token}', SurvivorDraftServer::class)->middleware(AuthenticateMcp::class);
