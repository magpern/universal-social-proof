# ADR-0015 — Settings storage, precedence, and master-enable

## Status

Accepted (M6/M7 architecture freeze)

## Context

M1–M5 exposed operator seams via options (`usp_retention_days`,
`usp_exclude_out_of_stock`) and filters (template, product exclusions, geo).
M6 needs a single operable admin surface without breaking filter contracts or
duplicating sources of truth.

## Decision

### Storage

- Persisted operator settings live in one structured option: **`usp_settings`**.
- Settings migration marker: separate option **`usp_settings_version`** (integer).
  M6 completes at version **`1`**.
- This is **not** `usp_db_version` / `Schema::DB_VERSION` (`20260829m1`).

### Keys (v1)

`display_enabled`, `template`, `excluded_product_ids`, `exclude_out_of_stock`,
`load_on_cart`, `load_on_account`, `geo_weighting_enabled`, `retention_days`.

### Migration

On first successful M6 settings migration: read legacy `usp_retention_days` and
`usp_exclude_out_of_stock`, validate/clamp into `usp_settings`, set
`usp_settings_version = 1`. Thereafter **`usp_settings` is the sole persisted
source of truth**. Do not indefinitely dual-read legacy options. Do **not**
delete legacy rows during migration (inert for rollback/audit). Idempotent:
never overwrite a valid `usp_settings` once version ≥ 1.

### Precedence

```text
code default → persisted usp_settings → existing apply_filters → clamp/validate
```

### Master enable (`display_enabled`)

Default **`true`**. **Display-only**: when false, toaster does not enqueue;
`GET /wp-json/universal-social-proof/v1/notifications` remains available and
returns **HTTP 200**, body **`[]`**, **`Cache-Control: no-store`**. No 204 or
new response contract. Capture, suppression, refunds, privacy, and retention
continue.

### Product IDs

UI search/select is convenience only. Server always normalizes, validates
positive integers, dedupes, and caps at 200.

## Consequences

Operators get a small settings surface; extensions keep filter overrides;
upgrade from v0.5.0 is one-shot and safe; REST contract stays stable when
display is disabled.

## Related

[M6-ADMIN-DIAGNOSTICS-PLAN.md](../milestones/M6-ADMIN-DIAGNOSTICS-PLAN.md) ·
[architecture/FROZEN.md](../architecture/FROZEN.md) §12 ·
[0012-admin-capability-menu.md](0012-admin-capability-menu.md)
