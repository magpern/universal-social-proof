# ADR-0004 — `occurred_at` vs `captured_at`

## Status

**Accepted** (M1 evidence — WooCommerce 11.0.1)

## Context

Relative time and freshness must reflect genuine commerce timing. Insert time alone breaks backfill/replay. Exact WooCommerce date sources vary by payment path (online, COD/manual, direct `completed`).

## Decision (frozen semantics — unchanged)

| Field | Meaning |
|-------|---------|
| `occurred_at` | Immutable authoritative **commerce** time for the purchase fact; persisted once; never updated on duplicate hooks or partial refunds |
| `captured_at` | USP insertion wall-clock; ops/diagnostics/backfill lag only |

Relative-time UI, freshness weights, and retention use **`occurred_at` only**.

## Exact deterministic resolver (M1)

Implemented in `UniversalSocialProof\Capture\OccurredAtResolver`:

1. `$order->get_date_paid( 'edit' )` when `WC_DateTime`
2. else `$order->get_date_completed( 'edit' )` when `WC_DateTime`
3. else `$order->get_date_created( 'edit' )` when `WC_DateTime`
4. else **`null` — fail closed; do not capture**

Never use `time()`, request “now”, `captured_at`, or `get_date_modified()` as `occurred_at`.

Persist non-null values as UTC MySQL `datetime` via `gmdate` / `DateTimeImmutable` UTC.

### WooCommerce 11.0.1 evidence

- Public getters return `WC_DateTime|null`.
- `date_paid` is set in `payment_complete()` / `maybe_set_date_paid()`; **not** at BACS/cheque checkout (`on-hold`); COD often lacks `date_paid` until later `completed`.
- No public status-transition timestamp API exists; do not invent one.
- Status hooks fire after persist; resolver reads props already on the order object.

## Consequences

Abnormal orders without any of the three timestamps are skipped (logged). Backfill remains possible with historical `occurred_at` and current `captured_at`.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §6 · [GOVERNANCE.md](../GOVERNANCE.md) §6 · [milestones/M1-CAPTURE-STORAGE-PLAN.md](../milestones/M1-CAPTURE-STORAGE-PLAN.md)
