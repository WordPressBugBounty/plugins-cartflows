---
module: admin-legacy-core
---

# admin-legacy-core

## Responsibility

Owns the legacy (pre-3.0) CartFlows admin UI: the WP admin menu and pages, the React settings and flow-editor bundles, and the two server transports those bundles talk to — roughly 40 `wp_ajax_cartflows_*` handlers and the `cartflows/v1` REST controllers — plus the field-definition arrays and the shared save/sanitize path in `inc/meta-ops.php`.

Ownership stops at the data layer: it never defines post types, capability semantics, templates, or front-end rendering. The new-UI admin, and the notice that offers to switch to it, live outside this directory.

## Why it is this way

This tree is selected at bootstrap by `CARTFLOWS_LEGACY_ADMIN`, computed from an option before `plugins_loaded`, and it is a near-identical fork of `admin-core/` — several files (`inc/log-status.php`, `inc/admin-menu.php`, `api/flow-data.php`) exist in both. A fix here almost certainly needs a mirrored fix there.

The mechanism that makes the module work is implicit: endpoint names, nonce actions and localized variable keys are all derived from method names, and the chain delivering a nonce to the browser spans include-time registration, an `add_filter` closure, and an `apply_filters` inside the enqueue. Nothing in any single file states that ordering, and every failure along it surfaces as the same "nonce validation failed".
