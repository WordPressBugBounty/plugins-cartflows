---
module: admin-core
---

# admin-core

## Responsibility

Owns the entire CartFlows 3.0 admin UI surface: the WP admin menu and pages, the React SPA bundles for the settings/editor/onboarding apps, and the two server transports those apps talk to — `wp_ajax_*` handlers under `ajax/` and `cartflows/v1` REST controllers under `api/` — plus the settings and meta field *schemas* and the shared read/write helpers.

Ownership stops at the data layer: every handler delegates domain work to plugin-root classes, and the module has no bootstrap of its own. It owns no front-end funnel rendering.

## Why it is this way

Endpoint names, nonce actions and localized variable keys are all *derived* from method names by `init_ajax_events()`, and the chain that delivers a nonce to the browser spans include-time registration → an `add_filter` closure → `apply_filters` inside the enqueue. Nothing in any single file states that ordering, and every failure along it looks identical: "nonce validation failed".

The two transports enforce authorization through completely different, non-shared mechanisms — per-handler `check_ajax_referer` plus `current_user_can` for AJAX, per-controller `permission_callback` for REST — with an unused base-class helper that looks like it applies but does not. Combined with a four-capability matrix that does not line up with the nonce-localization gate, the cost of a wrong guess here is a silent auth gap, not a stack trace.
