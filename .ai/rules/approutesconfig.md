---
paths:
  - '{app,routes,config}/**'
---

# Approutesconfig

## Filament is the only login; MCP auth is a separate token
Fortify and the starter kit's login/register/settings screens were removed — authentication is Filament's at /admin/login, and User implements FilamentUser returning true (the only accounts are the league's own). Vue surfaces sit behind the same `web` session guard Filament logs into. The MCP endpoint is deliberately outside the `web` group (routes/ai.php is registered by laravel/mcp with no middleware group), so it has no session auth and no CSRF — the connector handshake breaks if it does. It authenticates with MCP_TOKEN via bearer header or the `/mcp/{token}` path segment, and fails closed when no token is configured.
