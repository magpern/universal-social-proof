# Architectural Decision Records — Universal Social Proof

House format (Nygard): Status / Context / Decision / Consequences / Related.

| ADR | Title | Status | File |
|-----|-------|--------|------|
| 0001 | Plugin purpose and boundaries | Accepted (freeze) | [0001-plugin-purpose-and-boundaries.md](0001-plugin-purpose-and-boundaries.md) |
| 0002 | Soft UGC dependency / Null adapter | Accepted (freeze) | [0002-soft-ugc-dependency.md](0002-soft-ugc-dependency.md) |
| 0003 | Storage model and provenance/public projection | Accepted (freeze) | [0003-storage-provenance-public-projection.md](0003-storage-provenance-public-projection.md) |
| 0004 | `occurred_at` vs `captured_at` | **Accepted** (M1: paid→completed→created→null) | [0004-occurred-at-vs-captured-at.md](0004-occurred-at-vs-captured-at.md) |
| 0005 | Terminal suppression and idempotency | Accepted (freeze) | [0005-terminal-suppression-idempotency.md](0005-terminal-suppression-idempotency.md) |
| 0006 | Refund semantics and immutable quantity | Accepted (freeze) | [0006-refunds-immutable-quantity.md](0006-refunds-immutable-quantity.md) |
| 0007 | HPOS-safe personal-data erasure | **Accepted** (M1 dual-path + retrospective limitation) | [0007-personal-data-erasure.md](0007-personal-data-erasure.md) |
| 0008 | Cache-safe REST and input boundaries | Accepted (freeze) | [0008-cache-safe-rest-input-boundaries.md](0008-cache-safe-rest-input-boundaries.md) |
| 0009 | UUIDv4 public identifiers | Accepted (freeze) | [0009-uuidv4-public-identifiers.md](0009-uuidv4-public-identifiers.md) |
| 0010 | Selection pipeline and product-resolution budget | Accepted (freeze) + **M2 amendment 2026-08-30** | [0010-selection-product-resolution-budget.md](0010-selection-product-resolution-budget.md) |
| 0011 | Server-side template model | Accepted (freeze) + **M2 `message` clarification 2026-08-30** | [0011-server-side-templates.md](0011-server-side-templates.md) |
| 0012 | Admin capability and menu placement | Accepted (freeze) | [0012-admin-capability-menu.md](0012-admin-capability-menu.md) |
| 0013 | Version and release policy | Accepted (freeze) | [0013-version-release-policy.md](0013-version-release-policy.md) |
| 0014 | Extensibility boundary | Accepted (freeze) | [0014-extensibility-boundary.md](0014-extensibility-boundary.md) |

Authoritative specification: [../architecture/FROZEN.md](../architecture/FROZEN.md).
