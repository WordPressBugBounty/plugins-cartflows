---
module: webhook
---

# webhook

## Responsibility

Owns the full outbound webhook lifecycle: CRUD of webhook configs in a single `wp_options` row, mapping WordPress/WooCommerce actions to five named events, building the JSON payload, and delivering it HMAC-signed and asynchronously via Action Scheduler, with retries.

Ownership stops at the wire — it does not own the admin UI, nor the REST/AJAX endpoints that call `add_webhook` and `send_test`, nor Action Scheduler itself.

## Why it is this way

Delivery is deliberately asynchronous and the payload is built at *dispatch* time rather than at delivery time, so a retry cannot observe a mutated order. The cost is that a retry hours later resends stale state, and that the payload — including customer PII — is persisted in the Action Scheduler args table for the life of the action log.

The 60-second dedup transient exists because two WooCommerce order statuses (`processing` and `completed`) both map to `order_completed`. It narrows the window rather than closing it, so receivers must be idempotent on `order_id` regardless.
