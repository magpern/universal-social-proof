# ADR-0001 — Plugin purpose and boundaries

## Status

Accepted (architecture freeze)

## Context

Universal Social Proof must show recent genuine WooCommerce purchase activity without fabricating FOMO or becoming a general marketing-notification platform.

## Decision

- Portable standalone WooCommerce plugin named **Universal Social Proof** (`universal-social-proof`, namespace `UniversalSocialProof\`).
- **Genuine purchase events only.** No admin-fabricated or custom purchase-looking notifications.
- v1 geography is **country only** (purchase country from order data; optional visitor country via soft UGC). Region and city are **out of M0–M7**.
- No buyer IP geolocation for historical purchase notifications.
- No hard dependency on Telegram, UPR, or MPCF event buses.

## Consequences

Implementation must refuse fake purchase event features. Region/city require a future approved product milestone beyond this freeze.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §1, §17, §20 · [GOVERNANCE.md](../GOVERNANCE.md)
