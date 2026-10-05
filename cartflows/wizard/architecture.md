---
module: wizard
---

# wizard

## Responsibility

Owns the CartFlows first-run setup wizard: the standalone `index.php?page=cartflow-setup` admin screen (its own full HTML document, React SPA, and asset enqueue), the six-step flow, and the eight `wp_ajax_cartflows_*` handlers backing it.

Ownership stops at the data it writes — flow/step import mechanics, settings shape, and the template-preview iframe's own message handling all live outside this module.

## Why it is this way

The wizard is not a normal admin page. It renders its own `<html>` document, fires `admin_print_styles` and `admin_head` manually, and ends in `exit`, so nothing that assumes the standard admin lifecycle applies inside it.

The jQuery↔React boundary is entirely string-based: buttons carry CSS classes that a delegated jQuery handler binds, and the two halves communicate through custom DOM events with no registry. Rename a class or an event name and nothing errors — the wizard just stops.
