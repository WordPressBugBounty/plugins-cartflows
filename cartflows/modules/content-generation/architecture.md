---
module: content-generation
---

# content-generation

## Responsibility

Owns the CartFlows AI **account-connection handshake** and the **outbound transport to the credit server**: it builds the auth-portal URL, receives and decrypts the returned access key into the `cartflows_auth` option, exposes two REST routes under `/ai/auth`, and provides `send_get_request` / `send_api_request` helpers.

Ownership stops at the token — it never generates, prompts, or renders any AI content. Consumers of the credits API live outside this module, as does `ApiBase::validate_permission()`.

## Why it is this way

The access key is delivered by an external portal and arrives alongside its own encryption key, so possession of the payload is not proof of origin. The WordPress nonce is therefore the only thing establishing that this site started the flow — which is why the code comments call it mandatory rather than defensive.

Three separate load-bearing comments already exist in this source precisely because prior readers got the VIP argument positions, the nonce, and the key handling wrong.
