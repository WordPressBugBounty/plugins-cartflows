---
module: woo-dynamic-flow
---

# woo-dynamic-flow

## Responsibility

Owns the "start a funnel from a WooCommerce product" path: a per-product CartFlows product-data tab storing `cartflows_redirect_flow_id` and `cartflows_add_to_cart_text`, the `woocommerce_add_to_cart_redirect` interception that sends the buyer into the flow's first step, and two front-end shortcodes that render a Woo add-to-cart form inside a step page.

The funnel field is shared: Pro's Instant Checkout also reads `cartflows_redirect_flow_id` to choose which checkout Buy Now opens. So the redirect has its own opt-out (`cartflows_redirect_add_to_cart`), and the tab exposes `cartflows_product_tab_after_flow_field` so Pro can render its own opt-out beside it. Free never learns about the Pro setting.

Ownership stops at the redirect URL — cart configuration, step resolution, and the `cartflows_skip_configure_cart` consumer all live outside this module.

## Why it is this way

Every non-obvious behaviour here is an implicit cross-module contract rather than an API: the `cf-redirect` query arg is the cart-skip signal, two same-named redirect handlers at priorities 10 and 99 decide which flow wins, and the hidden fields depend on globals set elsewhere.

The shortcodes swap `$wp_query` because WooCommerce's single-product templates assume they are on a product page; there is no supported way to render that form off one. That is also why hook state is removed and restored by hand around the render.
