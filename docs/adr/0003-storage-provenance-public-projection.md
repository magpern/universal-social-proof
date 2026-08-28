# ADR-0003 — Storage model and provenance / public projection

## Status

Accepted (architecture freeze)

## Context

Storefront must not query WooCommerce order tables per page view. Lifecycle (refunds, cancel, erasure) needs order/item linkage that must never appear publicly.

## Decision

- Custom table `{prefix}usp_events` (not CPT, not order meta, not transient-only).
- Separate **internal provenance** (`source_order_id`, `source_item_id`, internal `id`, status) from **event/selection keys** and from the **public DTO**.
- Provenance never crosses the public boundary (REST, HTML, public diagnostics).
- v1 schema stores **country_code** only among geo fields — no `region_code` or city columns.
- Do not snapshot product display name; resolve current public presentation at serve time.
- Retention: configurable 7–90 days, default 60, keyed primarily on `occurred_at`.

## Consequences

Docs must not describe the whole row as a “privacy-safe snapshot.” Erasure and refunds use provenance; browsers see only the public projection.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §5 · ADR-0007 · ADR-0009
