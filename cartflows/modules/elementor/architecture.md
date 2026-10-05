---
module: elementor
---

# elementor

## Responsibility

Registers CartFlows' four Elementor widgets (`checkout-form`, `order-details-form`, `next-step-button`, `optin-form`) plus their category, control panels and CSS, and translates Elementor control values into CartFlows step settings — by runtime filters during render, and by writing step post meta on editor save.

Ownership stops at the shortcode boundary: every widget's `render()` emits `do_shortcode( '[cartflows_*]' )`. The actual checkout/optin/thank-you markup, WooCommerce templates and step logic live in the step modules.

## Why it is this way

The load path is the thing no reader can infer from the file layout: editor compatibility, the WooCommerce template override, and the thank-you demo-data filter all ship as a side effect of a `require_once` buried inside widget registration, which is itself gated on `global $post` being a CartFlows step.

Settings reach the shortcode through filters registered at render time rather than through arguments, because the shortcodes read step meta rather than accepting parameters. That indirection is what makes the static `$settings` aliasing and the never-removed filters possible.
