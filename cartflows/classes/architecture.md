---
module: classes
---

# classes

## Responsibility

CartFlows' core runtime: the plugin bootstrap (`Cartflows_Loader` — constants, file loading, activation), shared read-only accessors (`Cartflows_Helper`, `Cartflows_Utils`, the global `_wcf_*()` functions), request-scoped step state (`Cartflows_Step_Factory`, `$GLOBALS['wcf_step']`), frontend/session wiring, pixel tracking, logging, admin notices, version migrations, telemetry, and the template importer.

Learn-checklist completion is owned here rather than in the admin trees (`Cartflows_Learn_Progress`): the modern REST route that renders it and the analytics payload that reports it need the same answer, and the analytics filter runs on frontend requests where the admin tree's REST class is not the right dependency. The legacy admin tree keeps its own copy of the completion logic and is not instrumented; the resolver serves the modern tree and analytics only.

`Cartflows_Product_Search` lives here rather than in an admin tree because both admin trees run it and both are mutually exclusive at load time. It exists because WooCommerce's `search_products()` is the wrong tool for a picker: it matches each typed word against the title, the excerpt **or** the content and applies its cap as a SQL LIMIT, so on a large catalogue the database stopped looking once it had that many description matches and the product actually named what the merchant typed was never fetched. Ranking therefore happens in SQL, not in PHP — sorting the rows the database chose to return cannot rescue a row it never returned.

`Cartflows_Coupon_Search` sits beside it for the same reason, and exists as its own class rather than a method on the product helper because the two share only their caller. Both keep the admin trees as argument marshalling: the coupon SQL previously existed verbatim in both, which is how one cache-key defect became two.

Ownership stops at rendering and business logic: step-type behaviour lives in `modules/`, admin screens in `admin-core/` and `admin-legacy-core/`, page-builder shims in `compatibilities/`. This module loads those but does not implement them.

## Why it is this way

Correctness here depends almost entirely on **which of three bootstrap windows** a piece of code sits in — `plugins_loaded:99`, `init`, or `wp:1`. That single fact determines whether a filter applies at all, whether `$GLOBALS['wcf_step']` is populated, and whether a log line is written or silently dropped.

The two settings-getter families are the sharpest edge: they look identical and behave oppositely, because the tracking getters memoize eagerly to avoid re-reading options on every pixel call, while the general getters stay live until `wp` so the admin can change them mid-request. Neither behaviour is visible from a signature.
