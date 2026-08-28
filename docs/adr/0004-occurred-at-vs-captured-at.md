# ADR-0004 — `occurred_at` vs `captured_at`

## Status

**Accepted** for timestamp semantics (architecture freeze).  
**Exact deterministic resolver: Proposed / Requires M1 evidence** — do not invent during freeze or M0.

## Context

Relative time and freshness must reflect genuine commerce timing. Insert time alone breaks backfill/replay (old purchases would look new). Exact WooCommerce date sources vary by payment path (online, COD/manual, direct `completed`).

## Decision (frozen semantics)

| Field | Meaning |
|-------|---------|
| `occurred_at` | Immutable authoritative **commerce** time for the purchase fact; persisted once; never updated on duplicate hooks or partial refunds |
| `captured_at` | USP insertion wall-clock; ops/diagnostics/backfill lag only |

Relative-time UI, freshness weights, and retention use **`occurred_at` only**.

## Deferred to M1 (not decided here)

M1 must characterize WooCommerce 11 public APIs (HPOS-safe) and then **amend this ADR** (or add a follow-on ADR) with an exact deterministic resolver, conceptually:

1. paid timestamp when WooCommerce has one;  
2. otherwise a reliable public timestamp for the qualifying commerce state **if** WC exposes one;  
3. otherwise order creation time.

Required tests: normal online payment; COD/manual; direct transition to `completed`.

Do **not** prescribe a nonexistent “status transition timestamp” without API evidence.

## Consequences

M1 cannot be considered complete until the resolver is frozen from evidence. Backfill remains architecturally allowed with historical `occurred_at`.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §6 · [GOVERNANCE.md](../GOVERNANCE.md) §6 · [roadmap/README.md](../roadmap/README.md)
