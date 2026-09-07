# ADR-0018 — Event types, cart truth, and source freshness

## Status

Accepted (v1.1)

## Context

v1.0 stores purchase-only events. Operators need optional real add-to-cart
social proof with short freshness, without fabricating FOMO or weakening
purchase truthfulness.

## Decision

1. Persist explicit `event_type`: `purchase` | `add_to_cart`.
2. Cart events store no visitor/session/customer identifiers — only product,
   optional quantity, optional UGC country, timestamps, and status.
3. Cart truth is historical successful add; cart removal does not suppress.
4. Purchase retention remains days (7–90). Cart retention is minutes (5–1440),
   default 60.
5. Cart capture is off by default; when off, do not capture. Purchase capture
   continues when purchase display is disabled.
6. Selection prefers purchases, then fills with cart; single REST stream.

## Consequences

Schema and settings migrations required. Duplicate-hook protection is
request-local, not identity-based.

## Related

[V1.1-FEATURE-PLAN.md](../releases/V1.1-FEATURE-PLAN.md) ·
[0003-storage-provenance-public-projection.md](0003-storage-provenance-public-projection.md)
