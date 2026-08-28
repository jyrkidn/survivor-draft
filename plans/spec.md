# Survivor Fantasy Draft — build spec

Stack: Laravel + PostgreSQL, the Vue (Inertia) starter kit for the frontend, Filament for
admin. Three surfaces over one domain layer: an MCP server, a Filament panel, and a Vue app.
Built for exactly two managers, but the schema does not hardcode that.

---

## 1. What it is

A two-person fantasy league for Survivor. Before the season the two managers snake-draft
the cast. During the season, results are entered conversationally through Claude via MCP:
"Jelly went home, Kilby won immunity" → one tool call → standings update.

Season 51 premieres **Wednesday 23 September 2026**, 21 castaways, titled "The Open Era".
Draft night target: **19–20 September**.

---

## 2. Draft rules

- 21 castaways, 2 managers, 10 rounds → 20 picks. One castaway goes undrafted and scores
  for nobody.
- Coin flip. **The winner chooses either the first pick, or picks 2 and 3 back to back.**
  This is deliberate: in a two-person snake, slot 1 gets picks 1, 4, 5, 8… and slot 2 gets
  2, 3, 6, 7… and which is better depends on how flat the talent pool looks. Letting the
  flip winner choose removes the argument.
- Snake order: round 1 is slot 1 → slot 2, round 2 reverses, and so on.
- Picks are validated: right manager, right turn, castaway still available.
- `undo_last_pick` exists because draft night is chaotic.

---

## 3. Scoring

| Event | Points |
|---|---|
| Survived an episode | 1 |
| Won tribe immunity | 1 |
| Won individual immunity | 2 |
| Found an advantage | 2 |
| Found a hidden immunity idol | 3 |
| Won fire-making | 3 |
| Played an idol that voided votes | 5 |
| Made the merge | 5 |
| Sole Survivor | 25 |
| Runner-up | 15 |
| Third place | 10 |
| Fourth place | 5 |

**Survival rule:** a castaway banks 1 point for every episode they start and are not
eliminated in. Voted out in episode 5 → 4 survival points. Finalists get the full count.

**Tie-break:** highest-placing castaway wins.

Scoring values live in a table, not in code. Changing one re-scores the entire season.

---

## 4. The one architectural decision that matters

**Nothing stores a score.** The database stores an append-only event log plus a list of
aired episodes. Every point total — per castaway, per manager, per season — is derived on
read.

Consequences, all good:

- Survival points are never recorded. They are computed from the elimination event and the
  episode count. There is no way to forget to award them.
- A mistake entered in episode 4 and caught in episode 9 is fixed by deleting one event.
  Everything downstream recomputes.
- Changing a scoring rule mid-season is safe and retroactive.
- Castaway status (`in` / `out`), exit episode and placement are all derived, not columns.
  They cannot drift from the event log because they *are* the event log.

Cache the derived snapshot if it ever gets slow. It will not get slow — 21 castaways and
~14 episodes is a few hundred rows.

---

## 5. Data model

```
seasons          id, number (unique), title, premiere_date, roster_size, is_active, timestamps
managers         id, season_id, name, draft_slot        unique(season_id,name), unique(season_id,draft_slot)
castaways        id, season_id, name, nickname, age, occupation, hometown, tribe,
                 photo_path (nullable), photo_source_url (nullable), photo_fetched_at (nullable)
                                                        unique(season_id,name)
draft_picks      id, season_id, pick_number, round, manager_id, castaway_id, timestamps
                                                        unique(season_id,pick_number), unique(season_id,castaway_id)
episodes         id, season_id, number, title, air_date, notes, timestamps
                                                        unique(season_id,number)
events           id, season_id, episode_number, castaway_id, type, points (nullable), note, timestamps
                                                        index(season_id, episode_number)
scoring_rules    season_id, type, points, label         primary key(season_id, type)
```

`events.points` nullable = fall back to the season's rule. Set it only to override a single
event.

Event types: `survived` (rule only, never stored as a row), `tribe_immunity`,
`individual_immunity`, `advantage_found`, `idol_found`, `idol_played`, `made_merge`,
`fire_win`, `winner`, `runner_up`, `third`, `fourth`, `voted_out`, `quit`, `medevac`,
`lost_firemaking`.

Elimination types: `voted_out`, `quit`, `medevac`, `lost_firemaking`. Placement types:
`winner`, `runner_up`, `third`, `fourth`.

Note the ordering constraint on `set_draft_order`: swapping two managers' slots trips the
unique index, so write negative placeholders first, then the real values.

---

## 6. Derivation

For each castaway:

```
exit_episode  = min(episode_number) over elimination-type events, else null
placement     = the placement-type event, if any
survived      = exit_episode ? clamp(exit_episode - 1, 0, episodes_aired) : episodes_aired
points        = survived * rule('survived')
              + sum over events of (event.points ?? rule(event.type))
status        = exit_episode ? 'out' : 'in'
```

Manager total = sum over their drafted roster. Undrafted castaways are computed too, so the
board can show what the unpicked castaway would have been worth.

---

## 7. MCP server

Check whether `laravel/mcp` fits before hand-rolling — if it does, use it. If not, a single
controller handling JSON-RPC 2.0 over `POST /mcp` is about 80 lines. Requirements either way:

- Methods: `initialize`, `tools/list`, `tools/call`, `ping`. Return empty arrays for
  `resources/list` and `prompts/list` rather than method-not-found; some clients probe them.
- Notifications (no `id`) get `202` and no body.
- Tool failures come back as a **result** with `isError: true` and a plain-English message,
  not a JSON-RPC error. The model can recover from the former and not the latter.
- Auth: bearer token from env. Also accept the token as a path segment
  (`/mcp/<token>`) — Claude's custom connector UI takes a URL more easily than a header.
- Echo back the client's `protocolVersion` when it is one you support.

### Tools

| Tool | Arguments | Purpose |
|---|---|---|
| `get_scoreboard` | season? | Standings, rosters with per-castaway points, episodes aired, current lead |
| `get_draft_board` | season? | Pick order, picks made, who's on the clock, who's available |
| `make_draft_pick` | manager, castaway, season? | One pick, validated against the snake order |
| `undo_last_pick` | season? | Remove the most recent pick |
| `record_episode` | see below | The workhorse |
| `record_event` | castaway, type, episode?, points?, note? | Single event, for corrections |
| `list_events` | episode?, castaway?, season? | Find event ids |
| `delete_event` | event_id | Remove one event |
| `set_final_placements` | winner, runner_up, third, fourth? | Finale bonuses; replaces any existing |
| `list_castaways` | status?, season? | Cast with status, owner, points |
| `set_tribes` | assignments[], season? | So tribe immunity can be recorded by tribe name |
| `set_castaway_photo` | castaway, url, season? | Fetch and store one cast photo from a URL |
| `set_castaway_photos` | photos[] `{castaway, url}`, season? | Same, in bulk — one call for the whole cast |
| `get_scoring_rules` / `set_scoring_rule` | type, points, label? | Rules are data |
| `list_seasons` / `create_season` / `set_active_season` | — | Season 52 is one call, not a rebuild |
| `set_draft_order` | first_pick, season? | After the coin flip; refuses once picks exist |

### `record_episode`

Arguments: `episode` (required), `title`, `air_date`, `notes`, `merge` (bool), and name
arrays for `voted_out`, `quit`, `medevac`, `lost_firemaking`, `individual_immunity`,
`tribe_immunity`, `idol_found`, `idol_played`, `advantage_found`, `fire_win`.

Behaviour:

1. Upsert the episode row.
2. **Delete all existing events for that episode number, then re-insert.** Calling it twice
   for the same episode corrects rather than duplicates. This is the property that makes
   live-entry-while-watching safe.
3. Compute who was alive at the *start* of this episode, before applying its eliminations.
4. `merge: true` awards `made_merge` to everyone alive.
5. `tribe_immunity` entries match a tribe name first (awards every living member), falling
   back to treating the entry as a castaway name.
6. Awarding to someone already eliminated records it and returns a warning rather than
   failing — the entry is more likely mis-numbered than wrong.
7. Return the recorded events, warnings, new standings, and who is still in.

### `set_castaway_photo` / `set_castaway_photos`

Takes a castaway name and an image URL, downloads the image server-side, and stores it
locally. Bulk form takes an array so the whole cast is one call.

Behaviour:

1. Resolve the castaway by the same loose name matching as every other tool.
2. Fetch the URL with a timeout (5s connect, 15s total) and a normal browser
   `User-Agent` — some CDNs reject anything that looks scripted.
3. Validate before writing: `Content-Type` must be `image/*`, size capped at ~5 MB, and
   the bytes must actually decode as an image. Don't trust the extension or the header
   alone.
4. Store under `storage/app/public/cast/{season}/{castaway_id}.{ext}`, write `photo_path`,
   `photo_source_url` and `photo_fetched_at`. Re-running replaces the file.
5. **If the fetch fails, do not fail the tool.** Save `photo_source_url` anyway, return a
   warning naming the castaway and the reason. The board falls back to hotlinking that URL,
   so a blocked download degrades to a working image rather than a broken page.
6. Bulk form returns per-castaway results — the model needs to know which three of
   twenty-one didn't land, not just that "some failed".

**SSRF guard.** The URL is model-supplied, so it is untrusted input even though only two
people can reach this app. Require `https`, resolve the hostname first and reject private
and link-local ranges (10/8, 172.16/12, 192.168/16, 127/8, 169.254/16, `::1`, fc00::/7),
cap redirects at 3 and re-check the resolved address after each one. Laravel's HTTP client
won't do this for you.

Optional, and worth it if the images vary: resize to a max edge of 600px and re-encode to
WebP on ingest. That both normalises the board layout and strips any metadata riding along
in the original file.

### Name resolution

Every tool that takes a castaway name resolves loosely: full name, nickname, first name,
last name, then substring. One match wins. Zero matches errors. Multiple matches errors
**listing the candidates** so the model can ask which one.

This is what makes the thing usable by voice-of-Claude during an episode — "Jelly",
"Kilby", "Thien An" and "Rob" all need to land.

---

## 8. Surfaces

Three ways in, **one domain layer**. Everything goes through services —
`ScoringService`, `DraftService`, `EpisodeRecorder`, `PhotoIngestor`. MCP tools, Filament
actions and Inertia controllers are all thin callers. If pick validation lives in a Filament
action and gets reimplemented in an MCP tool, they will drift, and the drift will surface
mid-draft.

### Filament panel — admin

The fallback for anything not worth doing conversationally, and the repair shop when
something is wrong.

- Resources: Season, Manager, Castaway, Episode, Event, ScoringRule.
- Castaway resource: photo upload plus a "fetch from URL" action calling the same
  `PhotoIngestor` the MCP tool uses.
- Episode resource: a custom "Record episode" form mirroring `record_episode` — multi-selects
  of living castaways per category, one merge toggle. Same service call underneath.
- Event resource is the important one: a flat, filterable, deletable list of every event.
  When the scoreboard looks wrong, this is where you find out why.
- Points are **computed columns, never editable fields**. Filament must not offer a "total
  points" input; there is nowhere to store one.

### Vue frontend — the two of you

Inertia pages, auth from the starter kit. Both managers have accounts; a `manager_id` on
`users` links a login to a draft slot.

- **Cast** — grid of castaways with photo, name, age, occupation, tribe, status, owner.
  Sortable and filterable. Doubles as the draft-night reference.
- **Draft** — the live room. Whose pick it is, the board so far, available castaways,
  and a pick button that is disabled unless it is *your* turn. Undo available to both.
- **Scoreboard** — the two totals, both rosters with per-castaway points, an episode-by-episode
  point history, and who is still in.

Render the pick order as an actual serpentine — a two-column grid with a line threading
pick 1 → 20 down through it. The layout then shows what a snake draft *is*, which no table
does.

### Live sync during the draft

Two people picking from two devices, twenty picks over an hour. **Poll every 2–3 seconds.**
That is the whole solution. Reverb and websockets are more infrastructure than this problem
deserves.

The real guard is at the database: `unique(season_id, pick_number)` means a double-submit
loses the race rather than corrupting the board. Catch the constraint violation and return
"that pick was already made" instead of a 500.

---

## 9. Deployment

One container behind the existing reverse proxy, Postgres alongside. Env: `DB_*`,
`MCP_TOKEN`, `APP_URL`. Add the server in Claude as a custom connector pointing at
`https://<host>/mcp/<token>`.

Note the auth split: the Vue app and Filament sit behind session auth, the MCP endpoint
behind its bearer token. Make sure the MCP route is excluded from the auth middleware
group and from CSRF, or the connector handshake fails in a way that is annoying to debug.

---

## 10. Build order

1. Starter kit install, migrations, models, seeder for the Survivor 51 cast (21 names below).
2. `ScoringService` + tests. **Write the tests here, not later** — the survival edge cases
   (eliminated in episode 1, quit before merge, finalist) are where this breaks.
3. Filament panel over the raw resources. Gives you a working way to see and fix data
   before either of the nicer surfaces exists.
4. MCP transport + `tools/list` + `get_scoreboard`. Connect it to Claude and confirm the
   handshake before writing the other seventeen tools.
5. `DraftService` + draft tools + the Vue draft page. Run a mock draft against them, with
   both of you on separate devices — that is the only way the polling and the turn-locking
   get tested.
6. `EpisodeRecorder`, `record_episode`, the correction tools, and the Filament episode form.
7. Vue scoreboard and cast pages. Photo ingestion.

Deadline is the 19th for steps 1–5. Steps 6–7 can land any time before the 23rd; the
scoreboard genuinely can wait until after the premiere, since nobody has scored anything
until episode 1 airs.

