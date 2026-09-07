# ADR-0017 — Uninstall and USP data ownership

## Status

Accepted (M6/M7 architecture freeze)

## Context

M0 deferred uninstall. By M5, USP owns a custom table, options, and Action
Scheduler jobs. WordPress plugin convention and the privacy model require a
clear uninstall policy before production-recommended v1.0.0.

## Decision

Ship **`uninstall.php`** (M6) that removes **all USP-owned data**:

- `usp_settings`
- `usp_settings_version`
- legacy options (`usp_retention_days`, `usp_exclude_out_of_stock`, and any
  other USP-owned options)
- `usp_db_version` and schema migrate lock option
- `{prefix}usp_events` table
- USP scheduled Action Scheduler cleanup jobs / group

No administrator “retain event data” toggle in v1.

### Deactivation

Deactivation must **not** delete data. Reactivation must not multiply
scheduled jobs and must preserve settings/events.

### Migration vs uninstall

Normal M6 settings migration leaves legacy option **rows** inert and present
for rollback/audit. Uninstall is the path that deletes them.

## Consequences

Uninstall matches typical WP plugin expectations and privacy hygiene.
Operators who need a backup must export before uninstall.

## Related

[M6-ADMIN-DIAGNOSTICS-PLAN.md](../milestones/M6-ADMIN-DIAGNOSTICS-PLAN.md) ·
[0015-settings-storage-precedence.md](0015-settings-storage-precedence.md) ·
[0003-storage-provenance-public-projection.md](0003-storage-provenance-public-projection.md)
