# ADR-0002 — Soft UGC dependency / Null adapter

## Status

Accepted (architecture freeze)

## Context

Visitor-country weighting improves relevance but must not hard-require Universal Geo Context.

## Decision

- Soft dependency via `GeoContextAdapter` with a Null adapter when UGC is absent or incompatible.
- Prefer calling UGC’s public PHP API inside USP’s anonymous REST request.
- v1 uses visitor **country** only; ignore UGC region for matching.
- Never trust client-supplied country.
- M5 includes an acceptance gate that UGC resolves the requesting visitor correctly behind the real proxy during USP REST.

## Consequences

Plugin operates fully without UGC (global selection). Geo weighting is an enhancement, not a hard requirement.

## M5 appendix (2026-09-07)

Authoritative algorithm: [M5-GEOGRAPHY-UGC-PLAN.md](../milestones/M5-GEOGRAPHY-UGC-PLAN.md).

Summary:

- Soft `GeoContextAdapter` + Null adapter; call `universal_geo_get_country_code()` once per notifications REST request.
- Visitor country weights selection via **tiered pools** (preference); never hard-filters out global fallback; never trusted from the client.
- `PDP_SEARCH_CAP = 5` is **shared** across preferred×country and preferred×any-country search phases.
- `ProductResolutionBudget::MAX = 20` and product memoization are request-global; geo pools never reset them.
- Each `public_id` is attempted at most once per request across pools.
- Schema remains `20260829m1`; DEV EXPLAIN uses existing `status_country_occurred` / `status_product_occurred` range plans with hard LIMIT 20/80.
- Public DTO unchanged; `{{country}}` / `{{location}}` remain purchase-country tokens.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §9 · ADR-0008 · [milestones/M5-GEOGRAPHY-UGC-PLAN.md](../milestones/M5-GEOGRAPHY-UGC-PLAN.md)
