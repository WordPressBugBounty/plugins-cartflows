---
module: woocommerce
---

# woocommerce

## Responsibility

Drop-in replacements for 17 WooCommerce core templates across `checkout/`, `order/`, `cart/`, `global/` and `notices/`, swapped in by `Cartflows_Frontend::override_woo_template()` on the `woocommerce_locate_template` filter.

Ownership is markup and hook placement only — no business logic, no data mutation. The CartFlows-specific hooks these files emit are consumed entirely in `modules/checkout/`, and the override is scoped to `cartflows_step` pages, so My Account and standard Woo pages never see them.

## Why it is this way

The files *look* like inert copies of WooCommerce core, and that is the trap. Three behavioural divergences from core are invisible unless you diff against upstream: AJAX-gated `woocommerce_review_order_*_payment`, layout-gated `woocommerce_thankyou_{gateway}`, and a layout-gated success message. Each is a live support-ticket generator.

The override mechanic itself is the other thing a contributor cannot infer from the directory contents: dropping a file in here globally overrides theme templates on every flow step, because the filter replaces an already-resolved path rather than adding a fallback.
