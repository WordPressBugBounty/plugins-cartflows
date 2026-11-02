---
module: gutenberg
---

# gutenberg

## Responsibility

Owns the Gutenberg/block-editor surface for CartFlows step post types: registers four `wcfb/*` blocks (next-step-button, checkout-form, optin-form, order-detail-form), builds their editor bundle, generates per-block frontend CSS and Google-font links from saved block attributes, and serves the editor's live shortcode previews over admin-ajax.

Ownership stops at the shortcodes themselves — every preview and every frontend render is `do_shortcode( '[cartflows_*]' )`.

## Why it is this way

Three contracts here are invisible from any single file. The attribute schema is split across JS, PHP registration and PHP config, with a different consequence for each omission. The editor preview is an AJAX round-trip whose refresh depends on a hand-maintained diff list and an `isHtml` flag doubling as a loop guard, because the `withSelect` mapper that triggers it is supposed to be pure. And the frontend CSS/font pipeline has a strict `wp` → `wp_head:80` → `wp_head:120` → `wp_footer:1000` ordering over four static mutable globals, with the fonts collected as a side effect of CSS generation.

Much of `src/components/` and `src/controls/` is verbatim Spectra code, which is why the editor bundle depends on a Spectra-owned JS global.

Blocks, like the Elementor, Beaver Builder and Bricks widgets, render the `[cartflows_*]` shortcodes inside `the_content` before its `do_shortcode()` pass at priority 11, so that pass reads the rendered form and order details a second time. The shortcodes therefore return their HTML through `Cartflows_Helper::escape_shortcode_brackets()` rather than each block escaping its own output. The helper finds tags with `wp_html_split()`'s regex so it agrees with `do_shortcode()` on what is text, and writes brackets as `&#091;`/`&#093;`, a form `do_shortcode()` does not turn back into brackets. Inside script and style it only escapes a `[` that opens a registered shortcode (`\u005b`, CSS `\5b `), so inline code and JSON keep their values.
