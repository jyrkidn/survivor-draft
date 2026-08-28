---
paths:
  - '{app/Http/Controllers/DraftController.php,resources/js/pages/Draft.vue}'
---

# Pages

## The draft runs on one laptop, not two accounts
Both managers sit at the same screen on draft night. So `DraftController::pick()` assigns the pick to whoever `DraftService::onTheClock()` says it is — never to the logged-in user's linked manager — and every DRAFT button is live, tinted with the clock manager's colour and labelled "DRAFT FOR A/B". Do not reintroduce turn-locking against `auth()->user()->manager`, the "YOUR PICK / WAITING" banner split, or the disabled-button state: they all assume two devices. There is no polling on this page for the same reason; the Inertia POST returns fresh props, which is enough. The build spec's two-device polling design and the design spec's disabled-button rule are both superseded by this.
