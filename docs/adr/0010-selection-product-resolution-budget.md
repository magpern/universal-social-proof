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

---

## Amendment — 2026-08-30 (M2 implementation)

**Status:** Accepted (M2)

M2 freezes the following selection constants and algorithm (see [M2-SELECTION-REST-PLAN.md](../milestones/M2-SELECTION-REST-PLAN.md)):

- Global candidate LIMIT **80**; PDP preferred LIMIT **20**.
- Preferred and global pools stay **separate** through shuffle and product resolution.
- Hard USP-initiated `wc_get_product()` budget: **20** (memoized repeats do not increment).
- PDP preferred-search uncached candidate cap: **5**. Unused search capacity returns to the remaining budget.
- `K` default 5, clamp 1..10; unique `public_id`; duplicate products allowed.
- OOS exclusion default **OFF** (`usp_exclude_out_of_stock`).
- Freshness: `occurred_at >=` current retention cutoff in candidate SQL.
- Variation: live ineligible variation is skipped (no parent fallback); only an unresolvable variation may fall back to an eligible parent.

These numbers supersede the freeze-era “e.g. 50–100 / ≤15–20” examples for M2 implementation. The product-resolution budget remains an acceptance criterion.
