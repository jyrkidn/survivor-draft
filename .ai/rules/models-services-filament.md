---
paths:
  - 'app/{Models,Services,Filament}/**'
---

# Models Services Filament

## Nothing stores a score — every point total is derived
The database holds an append-only `events` log plus a list of aired `episodes`. Points, castaway status, exit episode and placement are all computed on read by ScoringService. Never add a points/total/status column, and never let Filament offer a "total points" input — `events.points` is a per-event override of the season rule, not a total. Survival points are never stored as events: they are derived from the earliest elimination event and the aired-episode count, so `EventType::Survived` is deliberately excluded from `EventType::storable()`. Consequences relied on: deleting one event re-scores everything downstream, and changing a `scoring_rules` row re-scores the whole season retroactively.
