---
module: bricks
---

# bricks

## Responsibility

Owns the CartFlows-side integration with the Bricks builder: three Bricks elements (checkout, optin, order-details) that wrap existing CartFlows shortcodes, one CartFlows dynamic-data tag, one frontend stylesheet, and the mirroring of builder settings into CartFlows step post meta and `cartflows_*_meta_*` filters.

Ownership stops at the shortcode boundary. It also does not decide whether it loads — `compatibilities/` includes the loader only when Bricks is active **and** the saved page builder is `bricks-builder`.

## Why it is this way

The module's real behaviour diverges sharply from what its file names suggest: it is not a self-contained set of builder widgets. It writes post meta from a render hook, installs process-wide filters that outlive the element, replaces core WooCommerce templates site-wide, and its dynamic-data parser rewrites unrelated content on the page.

None of those cross-cutting effects are discoverable from a single file, and several are guarded by conditions that live outside the module entirely.
