---
module: classes
---

# classes

## Responsibility

CartFlows' core runtime: the plugin bootstrap (`Cartflows_Loader` — constants, file loading, activation), shared read-only accessors (`Cartflows_Helper`, `Cartflows_Utils`, the global `_wcf_*()` functions), request-scoped step state (`Cartflows_Step_Factory`, `$GLOBALS['wcf_step']`), frontend/session wiring, pixel tracking, logging, admin notices, version migrations, telemetry, and the template importer.

Learn-checklist completion is owned here rather than in the admin trees (`Cartflows_Learn_Progress`): the modern REST route that renders it and the analytics payload that reports it need the same answer, and the analytics filter runs on frontend requests where the admin tree's REST class is not the right dependency. The legacy admin tree keeps its own copy of the completion logic and is not instrumented; the resolver serves the modern tree and analytics only.

Onboarding drop-off tracking is owned here as well (`Cartflows_Analytics`): the onboarding app only reports which step it shows, through an `admin-core` AJAX handler that calls `record_onboarding_step()`. Progress is stored for every site in `cf_analytics_flags`, and the drop-off events are derived from it inside the opted-in stats payload, which also runs on frontend requests. The legacy `wizard/` tree is not instrumented.

`Cartflows_Product_Search` lives here rather than in an admin tree because both admin trees run it and both are mutually exclusive at load time. It exists because WooCommerce's `search_products()` is the wrong tool for a picker: it matches each typed word against the title, the excerpt **or** the content and applies its cap as a SQL LIMIT, so on a large catalogue the database stopped looking once it had that many description matches and the product actually named what the merchant typed was never fetched. Ranking therefore happens in SQL, not in PHP — sorting the rows the database chose to return cannot rescue a row it never returned.

`Cartflows_Coupon_Search` sits beside it for the same reason, and exists as its own class rather than a method on the product helper because the two share only their caller. Both keep the admin trees as argument marshalling: the coupon SQL previously existed verbatim in both, which is how one cache-key defect became two.

`Cartflows_Knowledge_Base` owns the docs payload end to end — the queue callbacks, the fetch, the shape validation and the one-time purge of the rows #2290 left behind — while `Cartflows_Admin` owns only the decision to schedule. The split follows the two questions' different lifetimes: a callback has to exist on every request the queue can dispatch from, whereas scheduling is a policy question about which admin UI is active and only an admin request needs to answer it.

Which UI is active is nevertheless re-asked inside the callback, from the option rather than from `CARTFLOWS_LEGACY_ADMIN`. A schedule outlives the UI that created it: nothing used to cancel the weekly refresh when a site moved off the legacy admin, so the callback treats a run on a current-UI site as the signal to drop both its own schedule and the payload that schedule was maintaining. The payload goes with it because only the legacy Knowledge Base screen ever read those few hundred KB, and a large option left lying around on installs that do not read it is the complaint this work started from. The UI toggles do the same directly for every switch from here on; the callback is what reaches the installs that switched before this existed, without waiting for anyone to open wp-admin.

The one-time purge of the rows that defect left behind is gated on the `cartflows-kb-failed-actions-cleaned` option, not on a `version_compare` like its neighbours in `Cartflows_Update::init()`. A self-chaining batch job can stop early — a timeout, a memory kill, or a concurrent runner deleting a row the batch still holds — and by then `init()` has already written the new `cartflows-version`, so a version gate could never let the chain resume. The flag is written only by a run that leaves no failed rows behind, which also means installs that never hit the defect settle on one count query instead of a queued action. The chain continues on whether the remaining failed count actually fell, not on how many `delete_action()` calls returned without throwing: the two differ in both directions, because a concurrently removed row throws although it is gone, and a store can no-op on a row it cannot resolve without throwing at all. A strict decrease is the steady-state rule because it is the only one that terminates, but it cannot distinguish a store that genuinely removes nothing from a lock timeout on a 24k-row table, so a run that proves no progress buys a small, explicitly counted number of slower retries. The counter rides in the action's own arguments rather than an option, so a chain that dies mid-backlog does not leave a stale budget behind; the hook's duplicate guard and its unschedule both ignore arguments, so adding one does not change what they match.

Ownership stops at rendering and business logic: step-type behaviour lives in `modules/`, admin screens in `admin-core/` and `admin-legacy-core/`, page-builder shims in `compatibilities/`. This module loads those but does not implement them.

## Why it is this way

Correctness here depends almost entirely on **which of three bootstrap windows** a piece of code sits in — `plugins_loaded:99`, `init`, or `wp:1`. That single fact determines whether a filter applies at all, whether `$GLOBALS['wcf_step']` is populated, and whether a log line is written or silently dropped.

The two settings-getter families are the sharpest edge: they look identical and behave oppositely, because the tracking getters memoize eagerly to avoid re-reading options on every pixel call, while the general getters stay live until `wp` so the admin can change them mid-request. Neither behaviour is visible from a signature.
