---
paths:
  - 'app/Mcp/**'
---

# Mcp

## MCP tools need an explicit snake_case $name and fit one page
Laravel MCP derives a tool's name from `Str::kebab(class_basename())`, giving `get-scoreboard`. This server's contract is snake_case, so every tool sets `protected string $name = 'get_scoreboard';` explicitly. Also: `tools/list` paginates at 15 by default, which silently hid four of the 19 tools from clients that do not follow the cursor — SurvivorDraftServer sets `$defaultPaginationLength = 50`. Domain failures return `Response::error()` (a result with isError, which the model can recover from), never a JSON-RPC error; LeagueTool::handle() catches LeagueException and does this for you.
