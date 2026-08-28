---
paths:
  - 'resources/{js,css,views}/**'
---

# Jscssviews

## League screens: dark-only, IBM Plex, no shared layout
The three Inertia screens follow plans/handoff/design-spec.md. The palette lives in `@theme` in resources/css/app.css (ink/sand/mgr-a/mgr-b etc.) with `band-carved`, `chart-grid` and `photo-placeholder` as `@utility` classes — do not reintroduce the starter kit's shadcn greys for these pages. Dark only: app.blade.php hardcodes `class="dark"`, there is no appearance switcher, and app.ts sets `layout: () => null` because each screen carries its own carved header band. Manager colour is applied via a `--mgr` custom property plus `text-[color:var(--mgr)]`, never a dynamic class name, so Tailwind can still see every class statically.

Coral and gold never appear without a redundant channel, but that channel is the manager's **name**, not a slot letter — the design spec's `A ·`/`B ·` prefixes and `MANAGER A` kickers were dropped at the user's request. The name itself now carries the colour (scoreboard totals, roster headings, draft column headings, the `DRAFT FOR SARAH` button), and the draft board additionally carries ownership by column position. The single exception is the 20px badge on a cast tile, where no name fits: it shows the manager's initial from `LeaguePresenter::initials()`, which lengthens the prefix until the managers are distinguishable (Sam/Sara become SAM/SAR), plus a `title` naming them in full. Do not reintroduce A/B anywhere.
