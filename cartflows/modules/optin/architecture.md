---
module: optin
---

# optin

## Responsibility

Owns the CartFlows Optin step: it renders a stripped-down WooCommerce checkout (name + email only, no payment) through the `[cartflows_optin]` shortcode, preconfigures the cart with a single free virtual product, generates and caches the step's dynamic CSS, and defines the optin step's admin meta and design settings schema.

Ownership stops at the WooCommerce checkout pipeline itself — it never renders form fields or creates the order, and its only validation rejects an optin checkout whose cart has a charged item or a non-zero total. It bends Woo's hooks and then hands off to `[woocommerce_checkout]`. Loaded only when WooCommerce is active.

## Why it is this way

An optin step is a checkout with almost everything removed, and WooCommerce offers no supported way to render a partial checkout. The module therefore forces `woocommerce_is_checkout` true, empties the gateway list, and clears six hook stacks with `remove_all_actions()` at a `wp` priority high enough to run after everyone else has registered. Payment is the one thing it leaves to WooCommerce: WooCommerce accepts `?wc-ajax=checkout` on any URL and the cart is only rebuilt on a page load, so the module never turns the payment check off (WooCommerce already skips payment for a $0 cart and re-checks against the order total), and every optin checkout is checked server-side.

That approach is why so many of the constraints here are about *timing* rather than API: the priority-1000 teardown and the priority-1 cart configuration are a pair, and the two `is_wc_endpoint_url( 'edit-address' )` bail-outs exist purely because the billing filters registered here are global and would otherwise reshape an unrelated My Account page.
