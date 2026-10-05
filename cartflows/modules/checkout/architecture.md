---
module: checkout
---

# checkout

## Responsibility

Owns the CartFlows checkout step: the `[cartflows_checkout]` shortcode and its simple / modern / instant layout templates, the rewiring of WooCommerce's checkout hooks (field definitions, coupon field, shipping section, order-review table, order button), the checkout-specific AJAX endpoints, stamping `_wcf_checkout_id` / `_wcf_flow_id` onto the order, and the "global checkout" override that hijacks WooCommerce's own checkout page.

Ownership stops at the WooCommerce cart/session/order objects themselves — it reads and re-decorates them — at flow/step storage, and at the admin editor UI, for which it only supplies field arrays via `cartflows_admin_checkout_*` filters.

## Why it is this way

The module's real behaviour is not in its call graph. It is in hook registration *timing*: WooCommerce bakes checkout field definitions on first call to `get_checkout_fields()`, which can happen before `wp`, so two filters here are registered directly in constructors rather than on hooks. Deferring them to `init` or `wp` silently drops all field customization.

The rest follows the same pattern — wrapper divs opened and closed across four hooks at hand-tuned priorities, a cross-tab cart restore driven by cookie plus transient, and post-meta-cached CSS with version-only invalidation. Each constraint exists because a simpler version lost a race.
