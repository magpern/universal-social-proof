# ADR-0016 — Diagnostics privacy and query budget

## Status

Accepted (M6/M7 architecture freeze)

## Context

FROZEN §12 requires diagnostics (counts, oldest/newest `occurred_at`, schema,
UGC, cleanup) without order ids. Vague “index-friendly” language risked
expensive scans, capture-lag averages, or running diagnostics outside admin.

## Decision

### Execution scope

Diagnostics run **only** on the USP Social Proof admin/diagnostics surface.
Event diagnostic SQL MUST NOT execute on storefront, notifications REST,
capture, suppression, refunds, cleanup, or unrelated wp-admin/Woo screens.
Do not persist diagnostic snapshots.

### Allowed v1 event diagnostics (fixed set)

- active count  
- suppressed count  
- newest active `occurred_at`  
- oldest active `occurred_at`  

Aggregate SQL only. Use existing indexes where practical. Retention (7–90 days)
bounds growth. **No** schema/index changes solely for diagnostics without a
review stop.

### Hard zeros on event diagnostics

- `wc_get_product()` calls: **0**  
- `wc_get_order()` calls: **0**  
- no customer/order joins  
- no event-row materialization merely to count  
- no provenance display  

### Not required in v1

Capture-lag AVG / samples (`captured_at − occurred_at`) are **removed** from
required v1 diagnostics (supersedes the capture-lag phrase in FROZEN §12 for
v1 required fields).

### Cheap non-event health

Plugin version, `DB_VERSION`, WooCommerce status/version, HPOS, UGC
availability/version/API, template validity, effective settings, Action
Scheduler / cleanup health.

### Forbidden outputs

`source_order_id`, `source_item_id`, customer names/emails/addresses, IPs,
hidden provenance. No event browser in v1.

## Consequences

Operators see system health without privacy leakage or storefront cost.
FROZEN §12 diagnostics list is interpreted through this ADR for v1.

## Related

[M6-ADMIN-DIAGNOSTICS-PLAN.md](../milestones/M6-ADMIN-DIAGNOSTICS-PLAN.md) ·
[0003-storage-provenance-public-projection.md](0003-storage-provenance-public-projection.md) ·
[architecture/FROZEN.md](../architecture/FROZEN.md) §12
