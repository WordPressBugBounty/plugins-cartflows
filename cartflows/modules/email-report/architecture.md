---
module: email-report
---

# email-report

## Responsibility

Owns the weekly CartFlows stats email end to end: scheduling the recurring Action Scheduler job, deciding whether to send, rendering the HTML body from its own templates, sending via `wp_mail`, and handling one-click unsubscribe.

Ownership stops at the data — all numbers come from `AdminHelper::get_earnings()`, and Pro-only content is supplied by outside code through filters rather than computed here.

## Why it is this way

Rendering uses a `return include` + `ob_get_clean()` idiom with templates reading the caller's locals by scope inheritance, rather than a parameter array. That keeps the templates terse but makes the variable contract entirely implicit: every field has an `isset()` fallback, so a renamed local blanks a section rather than raising an error.

Three of the module's contracts fail silently in exactly this way — the `return include` idiom, the scope-inherited variables, and the newline-delimited recipients option. That is what makes them worth writing down.
