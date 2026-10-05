---
module: thankyou
---

# thankyou

## Responsibility

Owns the CartFlows Thank You step: the `[cartflows_order_details]` shortcode that renders post-purchase order details (classic WooCommerce template or the two-column "Instant" layout), the thank-you page's access guard and optional custom redirect, its dynamic CSS generation and caching, and the admin settings/design field schema for the step.

Ownership stops at the order object itself (WooCommerce), the step-meta storage layer, instant-checkout *header* markup and coupon/toggle text (both borrowed from the checkout module), and PRO/offer child-order creation.

## Why it is this way

The module's public surface is a shortcode with destructive, non-idempotent side effects — it clears the cart and session because a thank-you page is the end of a purchase and the cart must not survive it. That is correct on the real flow and dangerous everywhere else, which is why the same shortcode wipes a live cart when previewed.

The `wcf-tq-layout` string is effectively an undeclared enum branched on across six conditionals in three files with no default, so introducing a fourth layout means finding every one of them.
