---
paths:
  - 'resources/js/pages/**'
---

# Js Pages

## Every screen fills the browser width
No page is pinned to a fixed width. The design spec capped Scoreboard and Cast at 480px centred and drew Draft at a static 1440/1000 — the user overrode all of it: the layout must always use the full browser screen. So: no `max-w-[…]` on a page wrapper, no fixed card widths, and the phone layouts stay intact by growing rather than by being capped. Cast uses `grid-cols-[repeat(auto-fill,minmax(160px,1fr))]` so columns are added as room allows and a tile never drops below its phone size (the earlier bug was a viewport-keyed `min-[560px]:grid-cols-3` inside a 480px card, which made photos *smaller* on a bigger screen — 162px down to 139px). Scoreboard puts the verdict and both totals on one line at `lg` and the two rosters side by side at `md`. Draft stacks board-over-available below `xl` and goes side by side above it.
