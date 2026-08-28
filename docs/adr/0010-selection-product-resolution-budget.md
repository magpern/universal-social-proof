# ADR-0010 — Selection pipeline and product-resolution budget

## Status

Accepted (architecture freeze)

## Context

Resolving WooCommerce products for an entire 50–100 candidate pool risks N+1 load. Publishing the full pool to the browser over-exposes genuine purchase records.

## Decision

1. Indexed prefilter → candidate bound N ≈ 50–100.  
2. Weighted shortlist using stored dimensions → resolution budget (e.g. ≤ 15–20) **before** `wc_get_product`.  
3. Resolve products only for the shortlist; drop unpublished/private/deleted/excluded; OOS only if setting enabled (**default OFF**).  
4. Return K events (default 5, hard cap 10).

M2 must treat the product-resolution budget as an acceptance criterion (tests/guards).

## Consequences

Server-side selection; small REST payloads; bounded WC product loads per request.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §8, §14 · ADR-0008
