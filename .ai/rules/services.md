---
paths:
  - app/Services/DraftService.php
---

# Services

## The draft locks when the season starts
`isLocked()` is true as soon as the season has any aired episode, and `makePick()`, `undoLastPick()` and `setDraftOrder()` all refuse while it is. Enforced here rather than on the screen, so the Vue page, the MCP tools and Filament cannot drift apart on it.

Like everything else in this app the lock is derived, never stored — there is no `draft_locked` flag. That is deliberate: deleting a mistakenly recorded episode reopens the draft on its own, which is the only escape hatch and the one the error message names. Do not add a column for it.

The point of blocking undo as well as picking: once episodes are scored, moving a castaway between rosters silently rewrites history, because manager totals are derived from whoever owns the castaway now.
