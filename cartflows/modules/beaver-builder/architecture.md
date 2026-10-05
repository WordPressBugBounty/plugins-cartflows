---
module: beaver-builder
---

# beaver-builder

## Responsibility

Owns the CartFlows-specific Beaver Builder integration: four `FLBuilderModule` subclasses plus their BB settings forms, per-node CSS, and an editor-compatibility shim.

Ownership stops at the shortcode boundary — the modules render `[cartflows_checkout]` / `[cartflows_optin]` / `[cartflows_order_details]` and only *steer* the real step modules by pushing values through `cartflows_*_meta_*` filters. None of the actual checkout/optin/thank-you behaviour lives here.

## Why it is this way

Settings reach the renderer through filter closures registered at render time rather than by direct call, because the shortcodes read step meta rather than accepting arguments. That indirection is why the closures are never removed and why two modules of the same type on one page interfere.

Enablement depends on a request-scoped static cache of the step type plus a two-entry-point loader (`wp` and `admin_init`, both at priority 8), which is also why a plain `admin-ajax.php` request loads none of this.
