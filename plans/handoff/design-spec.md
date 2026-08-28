# Survivor Draft League — design spec

Companion to the build spec. Covers what the three screens look like, not how they work.
Reference implementation: `Survivor Draft League.dc.html` (open in any browser).
Assets: `assets/survivor-51-logo.png`, tokens: `tokens.css`.

Season 51, "The Open Era". Two users, behind a login, dark only.

---

## 1. Foundations

### Palette

| Token | Hex | Use |
| --- | --- | --- |
| `ink` | `#0a1f2c` | card surfaces |
| `ink-deep` | `#06161f` | page ground behind cards |
| `header` | `#0a2b2c` | carved header band (+ stripe overlay, below) |
| `chart-line` | `#2f6377` | card borders, snake thread, strong dividers |
| `chart-line-dim` | `#12313f` | row dividers, chart grid |
| `chart-line-empty` | `#1e4658` | dashed empty pick slots, disabled outline |
| `tribe-teal` | `#1d5b58` | photo borders, header rules |
| `sand` | `#efe2c0` | primary type, neutral primary button |
| `sand-muted` | `#a99f88` | secondary / meta type |
| `sea-green` | `#7fae9d` | tribe labels and the tagline, nothing else |
| `dead` | `#5b6f7a` | eliminated type |
| `dead-dim` | `#3f5b68` | eliminated meta, photo captions |
| `mgr-a` | `#ff6b4a` | Manager A (coral) |
| `mgr-b` | `#f2b134` | Manager B (gold) |

The two manager colours are the only saturated things in the app. Nothing else is allowed
to be bright.

Header stripe overlay:

```css
background: #0a2b2c;
background-image: repeating-linear-gradient(115deg, rgba(255,255,255,0.035) 0 2px, transparent 2px 9px);
border-bottom: 2px solid #1d5b58;
```

Draft-screen chart grid (that screen only):

```css
background-image:
  linear-gradient(#12313f 1px, transparent 1px),
  linear-gradient(90deg, #12313f 1px, transparent 1px);
background-size: 48px 48px;
```

### Type — IBM Plex, three roles

- **Display** — Plex Sans Condensed 700, uppercase. Tracking `0.03em` on names,
  `0.14em` on titles and labels. Sizes: 52 (scoreboard gap), 46 (on the clock),
  42 (pre-draft headline), 22 (screen titles, manager names), 16–17 (castaway names).
- **Body** — Plex Sans 400, 12–14px, `line-height: 1.65`, `text-wrap: pretty`. Used for
  exactly one paragraph (pre-draft "how it runs").
- **Mono** — Plex Mono 400/500 for **every number and every label**: pick numbers, points,
  episode numbers, ages, tribes, statuses, button text. Tracking `0.1–0.28em` on labels,
  none on figures. Mono on numbers is load-bearing — they should read as instrument output.

One font call:

```html
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Sans+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
```

### Shape, spacing, motion

- 4px base unit. Card padding 32 desktop / 16–18 phone. Row rhythm 8–10px.
- Square corners everywhere. 1px borders. One elevation: cards on `ink-deep`, no shadows.
- One animation in the whole app — `clockPulse`, a 2s coral ring on the active pick slot:

```css
@keyframes clockPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(255,107,74,0.45); }
  50%      { box-shadow: 0 0 0 6px rgba(255,107,74,0); }
}
```

### Logo

`assets/survivor-51-logo.png`. `height: 62px` in the desktop header, `34px` in phone
headers, never below 28px, never recoloured. The compressed snake glyph is the secondary
mark (favicon, tab):

```html
<svg width="34" height="34" viewBox="0 0 34 34" fill="none">
  <path d="M8 6 H26 V13 H8 V20 H26 V27 H8" stroke="#efe2c0" stroke-width="2" stroke-linejoin="round"/>
</svg>
```

---

## 2. Draft screen — desktop only (designed at 1440)

Four stacked bands inside one `ink` card with the chart grid and a `chart-line` border.

1. **Carved header** — logo, 1px×46 `tribe-teal` divider, "THE OPEN ERA — DRAFT" (22px,
   `0.14em`) over "OUTWIT · OUTPLAY · OUTLAST" (mono 10px, `0.28em`, sea-green);
   premiere date mono right.
2. **Turn banner** — 6px left border in the active manager's colour, background a
   horizontal wash of that colour 14% → 0. Left: mono "ON THE CLOCK" kicker +
   46px name, with "— YOUR PICK" in the manager colour when it's you. Right: mono
   `PICK 09/20` and `ROUND 05/10`, the denominator in `dead`.
3. **Body** — two columns split by a 1px `chart-line` rule: the 560px snake board, then
   the available grid.
4. **Available grid** — header row "AVAILABLE" + mono `13 OF 21`, then 5 columns,
   `gap: 18px 16px`: a 4:5 photo tile, name, mono `age · OCCUPATION`, mono tribe, and a
   full-width DRAFT button per tile.

### The snake board (fixed geometry — do not make it responsive)

- Container 560 × 620, `position: relative`.
- Column centres x = **140** and **420**. Row centres y = **31 + 62n**, ten rows
  (31, 93, 155, 217, 279, 341, 403, 465, 527, 589).
- Cells 220 × 46, `left: 30` (A) or `310` (B), `top: y − 23`.
- Pick n → row `ceil(n/2)`; column B when `n mod 4` is 2 or 3, else column A.
- One SVG path in `chart-line` draws the full serpentine; a second path in `sand` at 55%
  opacity is drawn over it up to the last completed pick, so the line *is* the progress
  bar. 4px filled dot at pick 1, hollow dot at pick 20.

```
M140,31 H420 V93 H140 V155 H420 V217 H140 V279 H420 V341 H140 V403 H420 V465 H140 V527 H420 V589 H140
```

Both the path and the cell coordinates must come from the same constants file so they
cannot drift.

**Cell states**

- *Made* — fill `#08202c`, 1px `chart-line`, 3px left border in the owner's colour,
  mono pick number in that colour, name 16px sand, mono `TRIBE · AGE` in sand-muted.
- *On the clock* — fill `#12262f`, 1px `mgr` border, `clockPulse`, mono pick number left
  and mono "ON THE CLOCK" right, both in the manager colour.
- *Future* — 1px dashed `chart-line-empty`, mono number in `dead`, nothing else.

Column headers above the board: mono `MANAGER A` in coral, 22px name, mono `4 PICKED`.
Ownership reads by column position and letter before it reads by colour.

### Pre-draft state (the first thing they see)

Same header. Card narrows to 1000px.

- Banner: sand left border, mono "DRAFT NOT STARTED", 42px "SARAH PICKS FIRST", one sand
  `OPEN THE DRAFT` button on the right.
- Board fully dashed except pick 1 — 1px sand border, `#12262f` fill, mono "FIRST UP".
- Available grid replaced by a 300px column: "HOW IT RUNS" + one body paragraph, then a
  rule and three mono stat rows (cast locked 21, picks each 10, days to premiere).

---

## 3. Scoreboard — 390 phone-first

Strict order, because it must answer "am I winning" in the first 200px.

1. Header: logo + "SCOREBOARD", mono `AFTER EP 07` right.
2. **Verdict** — 52px signed gap in the leader's colour, then "SARAH LEADS" at 22px.
3. **Totals** — two rows: mono `A · SARAH` label in the manager colour, 26px mono total in
   sand, 8px bar on `#0d2836`. Bars scale to the leader (100% / 84%).
4. **Gap by episode** — 7-column grid, 56px tall, bars bottom-aligned, each coloured by who
   led that week; signed mono values in a matching grid below.
5. **Rosters** — A then B, each headed by the manager label and mono `6 ALIVE`. Rows:
   6px status square (filled sand = alive, 1px `dead` outline = out), name, mono `EP6` when
   out, mono points.

Sorted by points descending. Eliminated castaways stay in place, struck through in `dead`.
Points never change column.

---

## 4. Cast — 390 phone-first

Header + tribe filter row (`ALL / LOMANI / YASI / NAMOSI`; active sand, rest `dead`).
Then a 2-column grid, 14px gap, 16px padding.

Tile: 4:5 photo, 1px `tribe-teal` border, mono `PRESS STILL` caption bottom-left,
manager badge top-right — 20×20 solid square in the manager colour with the letter in
`ink`, or a dashed `dead-dim` square with `–` if undrafted. Below the photo: name (17px),
mono `age · OCCUPATION`, mono tribe in sea-green.

Eliminated: 66% `ink` overlay on the photo, border drops to `chart-line-dim`, full-width
`OUT · EP6` strip along the bottom in mono `dead`, name struck through.

Sorted by tribe then draft position. Filters subset, never reorder.

---

## 5. States, accessibility, responsive

- **Pick action** is coral and enabled only on your turn; otherwise transparent with a 1px
  `chart-line-empty` border, `dead` text, `cursor: not-allowed`. Never hidden — the
  disabled state is how you know it isn't your turn.
- **Colour-blind rule** — coral and gold never appear without a redundant channel: column
  position on the board, an `A ·` / `B ·` prefix in rosters and totals, the letter inside
  the badge on cast tiles. Elimination is carried by strike-through and `OUT · EPn`, never
  by the grey alone.
- **Edge cases** — a draft with 0 picks is the pre-draft state; a tie shows `LEVEL` in sand
  instead of a signed number; before the premiere the Scoreboard shows both rosters at 0
  and replaces the gap block with mono `SEASON STARTS 23 SEP`.
- **Responsive** — Scoreboard and Cast fluid 320–768, capped at 480 and centred above that;
  Cast goes 3 columns above 560. The Draft screen does not adapt below 1280 — show a mono
  notice to open it on the laptop rather than reflowing the snake.

---

## 6. Handoff notes — Laravel / Vue starter kit

- Register the palette in the Tailwind theme; the header stripe and the chart grid as two
  utility classes. Everything else is stock Tailwind spacing. `tokens.css` has both as
  CSS custom properties if you prefer that route.
- shadcn-vue: use `Button` (three variants — sand primary, coral action, disabled outline)
  and `Tabs` for the cast filter. Skip `Card`, `Table` and `Badge`; the borders and mono
  labels here are lighter than the defaults, and fighting them costs more than writing the
  divs.
- Components worth existing: `SnakeBoard`, `PickCell`, `CastTile` (shared by Draft and Cast
  with a `size` prop), `ManagerTotal`, `GapChart`, `RosterRow`.
- Photos: 4:5 crop, served at 2× the largest use (≈344px wide), one shared striped
  placeholder until the press stills land.

## 7. Open items

- The real S51 cast and press stills aren't out yet — names, ages, occupations, points and
  eliminations in the mock are placeholders.
- Scoring rules aren't in this spec. Once they exist, a per-castaway detail view that
  explains a number is the obvious next screen.
