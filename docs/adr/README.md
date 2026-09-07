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
| 0011 | Server-side template model | Accepted (freeze) + **M2 `message` clarification 2026-08-30** + **M4 `show_relative_time` / location alias 2026-08-31** | [0011-server-side-templates.md](0011-server-side-templates.md) |
| 0012 | Admin capability and menu placement | Accepted (freeze) | [0012-admin-capability-menu.md](0012-admin-capability-menu.md) |
| 0013 | Version and release policy | Accepted (freeze) + **M6/M7 amendment 2026-09-07** (no required public `v0.6.0`) | [0013-version-release-policy.md](0013-version-release-policy.md) |
| 0014 | Extensibility boundary | Accepted (freeze) | [0014-extensibility-boundary.md](0014-extensibility-boundary.md) |
| 0015 | Settings storage, precedence, master-enable | Accepted (M6/M7 freeze) | [0015-settings-storage-precedence.md](0015-settings-storage-precedence.md) |
| 0016 | Diagnostics privacy and query budget | Accepted (M6/M7 freeze) | [0016-diagnostics-privacy-budget.md](0016-diagnostics-privacy-budget.md) |
| 0017 | Uninstall and USP data ownership | Accepted (M6/M7 freeze) | [0017-uninstall-data-ownership.md](0017-uninstall-data-ownership.md) |
| 0018 | Event types, cart truth, and source freshness | Accepted (v1.1) | [0018-event-types-cart-freshness.md](0018-event-types-cart-freshness.md) |
| 0019 | Appearance and custom CSS | Accepted (v1.1) | [0019-appearance-custom-css.md](0019-appearance-custom-css.md) |

Authoritative specification: [../architecture/FROZEN.md](../architecture/FROZEN.md).  
Combined program: [../milestones/M6-M7-V1-PROGRAM.md](../milestones/M6-M7-V1-PROGRAM.md).  
v1.1 feature plan: [../releases/V1.1-FEATURE-PLAN.md](../releases/V1.1-FEATURE-PLAN.md).
