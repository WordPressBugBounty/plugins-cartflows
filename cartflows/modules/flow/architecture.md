---
module: flow
---

# flow

## Responsibility

Owns the two CartFlows custom post types (`cartflows_flow`, `cartflows_step`), their two taxonomies, the step URL/rewrite layer, and the two front-end page templates that render a step.

Ownership stops at rendering the step shell — step *content* (checkout, optin, thank-you logic), settings storage, and the admin funnel UI live outside this module. It only exposes the abstract `Cartflows_Step_Meta_Base` contract for them.

## Why it is this way

The module is small, but nearly every non-obvious line is defensive scar tissue whose reason lives outside the code. Removing the permalink base means claiming a site-wide catch-all rewrite rule, and the five bail conditions guarding it each close a specific reported bug rather than expressing a general rule.

Hook order is require order here: every class self-instantiates at include time with hook registration in its constructor, so there is no way to load a class without registering its hooks.
