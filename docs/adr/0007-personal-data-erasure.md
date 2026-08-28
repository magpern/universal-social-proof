# ADR-0007 — HPOS-safe personal-data erasure

## Status

**Proposed / Requires M1 evidence** for exact WooCommerce order-query APIs and exporter payload details.  
Architectural integration pattern is frozen.

## Context

`usp_events` retains `source_order_id` for lifecycle integrity. WordPress personal-data requests are keyed by email/user identity; USP has no email column and cannot map identity → rows alone.

## Decision (frozen pattern)

1. Register WordPress personal-data eraser/exporter (align with WooCommerce order personal-data handlers where appropriate).  
2. Resolve in-scope order ids via **HPOS-safe WooCommerce order query APIs** from the request identity.  
3. Hard-delete USP rows where `source_order_id` ∈ that set.  
4. Fail closed if orders are already gone (no email scan of USP table).

## Requires M1 evidence (do not invent now)

- Exact `wc_get_orders` (or equivalent) arguments for WC 11 + HPOS.  
- Exporter minimal payload wording.  
- Ordering relative to WooCommerce’s own order eraser.

## Consequences

Erasure is correct only when WC order lookup succeeds. Pattern is mandatory; concrete API calls are an M1 characterization deliverable.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §7 · ADR-0003
