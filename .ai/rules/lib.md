---
paths:
  - resources/js/lib/draftBoard.ts
---

# Lib

## Draft board geometry is fluid and single-sourced
Cell positions, the round connectors and the progress spine all come out of one `geometryFor(containerWidth, totalPicks)` result — never hardcode any of them, or they drift.

The draft order is **straight alternating**, not a snake: slot 1 takes the odd picks, slot 2 the even ones, and nobody ever picks twice in a row. The board is therefore a ladder — one row per round, the pair joined by a connector — not a serpentine. The old snake path and its `columnFor` rule (`n % 4` in 2 or 3) are gone; do not reintroduce them, and do not restore the design spec's "the winner chooses the first pick, or picks 2 and 3 back to back" coin-flip framing, which no longer applies.

Only the horizontal axis flexes: row pitch (62px) and cell height (46px) are fixed so the type inside a cell never scales down. The two columns are positioned off the *cell width*, not off the container, so the gap stays in proportion on a wide screen, and the round-number gutter travels with the cell block rather than being pinned to the container edge. Below a 168px cell the board goes `compact` and drops the tribe/age line instead of truncating it.

The spine down the gutter is the progress bar — it fills to the current round, the way the serpentine thread used to. Column position is the same thing as draft slot (A = slot 1), which is what carries ownership without colour. The board reflows at any width: the design spec's "open it on a laptop below 1280" notice was dropped because every screen must use the full browser width.
