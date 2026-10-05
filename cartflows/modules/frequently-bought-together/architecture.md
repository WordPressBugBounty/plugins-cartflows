---
module: frequently-bought-together
---

# frequently-bought-together

## Responsibility

Owns the Frequently Bought Together feature end-to-end in CartFlows **free**: the `_cartflows_fbt` product-meta blob (shape, sanitisation, read/write API), the WooCommerce product-editor tab and its save path, the single-product-page widget render and its own add-to-cart AJAX, the SQL that feeds FBT numbers into BSF Analytics, and the in-admin activation nudges (products-list notice, self-expiring NEW tab badge, Free-tier panel callout).

It also owns the companion **rules** for every caller, not just its own widget: what counts as a configured companion, how a variation resolves, what refuses to be added from a widget at all. Pro's Instant Checkout popup builds its cart lines by calling into this module rather than reimplementing the checks, so a rule fixed here is fixed on both paths. Two rules stayed with Pro and differ from what the widget does: the popup always passes an empty bundle key, so a merchant's `add_separately = no` is not honoured there, and it caps a request at its own limit rather than `max_products()`.

Ownership stops at the Auto Suggest *data* — Pro owns the REST route and the drawer/linked-products markup, which this module only styles and drives — plus account auth, the analytics transport, and the cart/order machinery itself.

## Why it is this way

Pro consumes this module through two seams — `build_add_queue()` and `window.wcfFbt.getSelection()` — rather than reading the widget's DOM and building its own cart lines. A copy would have to reproduce membership, variation resolution, the own-page backstop, the attribution marker and the manual validation pass, from a plugin that cannot see the markup they depend on: the variation select is a *sibling* of its row, not an ancestor, so a copied reader drifts on any markup change, silently.

The builder returns per-item outcomes rather than one verdict because the two callers must differ. This module's endpoint is all-or-nothing — a rejected companion must not leave a half-built bundle. The popup keeps what succeeded and names what it dropped, because a shopper mid-purchase should not be sent back to the product page over one out-of-stock companion.

That all-or-nothing guarantee covers the rules stage only. Once the queue is built, this module's endpoint ignores an `add_to_cart()` refusal as long as one line went in, so an out-of-stock companion is dropped silently under a success notice — long-standing, and the one place the popup behaves better than the widget it reads from.

This module is also one half of a free/Pro split it cannot see: free JS calls a Pro REST route and binds to markup Pro renders, and the free `SOURCE_VALUES` whitelist silently voids a mode the free UI still offers.

The nudges live free-side but are not free-only: Pro users run this plugin too, so the notice and badge render for every tier and only the panel callout is gated to Free. That gate exists because Pro already ships its own once-per-user driver.js tour over this same panel, so a second Free-shaped pointer would nag the same user twice on one screen. The nudges also read licence state through Pro's `cartflows_pro_is_active_license()` rather than free's own helper, which returns a raw status string that stays truthy after deactivation.

Around that sits a dense layer of theme-specific and WooCommerce-core-specific workarounds — Astra sticky bars, the block-theme loop exemption, the stripped `add-to-cart` field, the observer self-exclusion, Blocksy's button argument. Each looks like removable cruft to anyone who does not know which bug it closes.
