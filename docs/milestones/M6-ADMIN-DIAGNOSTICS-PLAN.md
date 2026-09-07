# M6 — Admin UX and Diagnostics Plan (FROZEN)

> **Status: FROZEN / APPROVED FOR IMPLEMENTATION PLANNING**  
> **Planning review verdict:** READY TO FREEZE (post narrow correction 2026-09-07)  
> **Internal target runtime:** `0.6.0` (**no** required public `v0.6.0` tag/Release)  
> **Baseline `main`:** `ce1aa0df0206516beb9625546eaa743c3f488578`  
> **Latest published release:** `v0.5.0`  
> **DB_VERSION:** `20260829m1` (**unchanged**)  
> **Program:** [M6-M7-V1-PROGRAM.md](M6-M7-V1-PROGRAM.md)

Authoritative M6 specification under `docs/architecture/FROZEN.md` and ADRs
0012, 0015, 0016, 0017. Do not reopen frozen decisions during implementation
unless repository evidence exposes a genuine contradiction.

---

## 1. Objective

Make the complete M1–M5 USP runtime **safely operable** by a WooCommerce
administrator without SSH/DB inspection.

Not a page builder, analytics product, notification designer, or fake-social-proof
generator.

Administrators must answer:

- Is USP display enabled?
- What will it display (template)?
- Where will it display (page gates)?
- Which products are excluded / OOS policy?
- Is country weighting enabled?
- How long are events retained?
- Is the system healthy (diagnostics)?
- Is UGC available?
- Why might notifications not appear?

---

## 2. Non-goals

- Public `v0.6.0` tag / GitHub Release / private publish / production deploy
- Event browser / provenance UI
- Capture-lag AVG metric
- Timing/delay/gap/motion UI knobs
- Weighting percentages / region / city
- Fabricated purchase notifications
- Arbitrary HTML / WYSIWYG template editor
- Schema / index migration for diagnostics
- Deleting legacy options during migration
- Changing REST status/shape when display disabled (no 204)

---

## 3. Current-state inventory (v0.5.0)

| Subsystem | Impl | Config today | Admin | Diag | Tests | M6 need |
|-----------|------|--------------|-------|------|-------|---------|
| Capture / suppress / refunds | yes | none | no | no | yes | none (lifecycle continues when display off) |
| Privacy export/erase | yes | WP Privacy | text only | no | yes | Privacy section links + uninstall |
| Retention | yes | `usp_retention_days` + filter | no | no | yes | UI + migrate into `usp_settings` |
| Selection / PDP / OOS | yes | OOS option + filter | no | no | yes | OOS UI |
| Templates | yes | filter-only | no | no | yes | persist + validate |
| Product exclusions | yes | filter-only (max 200) | no | no | yes | UI + server ID validation |
| Page targeting | yes | hardcoded denylist | no | no | yes | cart/account toggles |
| Frontend timing | yes | constants | no | no | yes | **not exposed** |
| UGC weighting | yes | filter | no | no | yes | toggle |
| REST + no-store | yes | architecture | no | no | yes | master-disable → `200 []` |
| Packaging / updates | yes | CI | n/a | n/a | CI | n/a |
| Admin / diagnostics | **no** | — | — | — | Admin forbidden | **this milestone** |

---

## 4. Configuration seam classification

### A — M6 admin (persisted in `usp_settings`)

| Key | Type | Default | Validation | Runtime consumer |
|-----|------|---------|------------|------------------|
| `display_enabled` | bool | `true` | bool | enqueue + REST empty path |
| `template` | string | M4 translated default | `TemplateSettings::validate_template()`; max 500; allowlisted tokens only | `TemplateSettings` |
| `excluded_product_ids` | int[] | `[]` | positive ints; dedupe; max 200 | `ProductTargetingPolicy` |
| `exclude_out_of_stock` | bool | `false` | bool | `StockExclusionSettings` |
| `load_on_cart` | bool | `false` | bool | `TargetingPolicy` |
| `load_on_account` | bool | `false` | bool | `TargetingPolicy` |
| `geo_weighting_enabled` | bool | `true` | bool | `GeographyPolicy` |
| `retention_days` | int | `60` | clamp 7–90 | `RetentionSettings` |

### B — filter-only (not UI)

`usp_geo_context_adapter`; all existing `apply_filters` after settings (final override).

### C — immutable architecture

CandidateQuery limits, ProductResolutionBudget, K≤10, checkout hard-deny, DTO allowlist, relative-time buckets, purge batch size.

### D — diagnostic-only

See §12. Not stored as configuration.

### E — not configurable in v1

Toaster timing, `MAX_BATCHES`, `FETCH_MS`, weighting %, region/city, fake events, event browser, delete-all-events, rate limiter.

---

## 5. Settings storage and migration

### Options owned by M6

| Option | Purpose |
|--------|---------|
| `usp_settings` | Structured array — **sole persisted source of truth** after migration |
| `usp_settings_version` | Integer; `1` when M6 settings migration complete. **Not** `DB_VERSION`. |

`usp_db_version` / `Schema::DB_VERSION` remains `20260829m1`.

Autoload: yes (small payload). Diagnostics never stored as options.

### Precedence

```text
code default → persisted usp_settings → existing apply_filters → clamp/validate
```

### Migration (one-shot, idempotent)

When `usp_settings_version < 1` or missing:

1. If valid `usp_settings` already present → set version `1` if needed; **do not overwrite**.
2. Else read legacy `usp_retention_days`, `usp_exclude_out_of_stock`.
3. Validate/clamp into new model (invalid field → validated default).
4. Write complete `usp_settings` (defaults + migrated fields).
5. Set `usp_settings_version = 1`.
6. Thereafter read **only** `usp_settings` as persisted base.
7. **No** indefinite dual-read.
8. **Do not** delete legacy option rows (inert for rollback/audit).
9. Corrupt/partial: fail safely to validated defaults; never repeatedly overwrite a valid `usp_settings` once version ≥ 1.

### Migration tests

- no legacy values  
- valid legacy values  
- invalid legacy values  
- migration repeated  
- existing `usp_settings` already present  
- filter override after migration  

### Reset to defaults

Capability + nonce + explicit confirmation. Resets `usp_settings` only. Does **not** delete event data.

---

## 6. Master enable / display_enabled

Default: **`true`**. Semantics: **DISPLAY-ONLY**.

When `false`:

| Surface | Contract |
|---------|----------|
| Frontend | Do not enqueue / start / display toaster |
| REST | Route remains available: `GET .../notifications` |
| Response | **HTTP 200**, body **`[]`**, **`Cache-Control: no-store`** |
| Forbidden | HTTP 204; new status/shape; treating disable as error |
| Lifecycle | Capture, terminal suppression, refunds, privacy exporter/eraser, retention/cleanup **continue** |

Unit + integration acceptance required.

---

## 7. Admin location and security

- Menu: WooCommerce → **Social Proof** (`add_submenu_page( 'woocommerce', … )`) — UPR pattern
- Capability: **`manage_woocommerce`** (ADR-0012)
- Nonces on all mutating actions
- Sanitize / validate / escape; success and error admin notices
- Unauthorized users cannot access

---

## 8. Section UX

| Section | Contents |
|---------|----------|
| General | `display_enabled` master |
| Display | Short help: presentation timing not operator-configurable in v1 |
| Content / Templates | Textarea; current + default; allowed-token help; reject invalid/unknown tokens |
| Products | WC enhanced product search/select (**convenience only**) + OOS toggle |
| Targeting | `load_on_cart`, `load_on_account` (defaults off); checkout shown as **hard-denied** (not configurable) |
| Geography | `geo_weighting_enabled`; copy: visitor geo from UGC; USP does not IP-geolocate; visitor country not persisted; purchase country from Woo order; UGC unavailable → global fallback |
| Privacy | Retention days (7–90); link to WP Privacy tools; no custom eraser UI |
| Diagnostics | Read-only (§12) |

No event browser in v1.

---

## 9. Template admin

Reuse M4 grammar / `TemplateSettings`. Allowed tokens: `{{product}}`, `{{country}}`, `{{location}}`, `{{time_ago}}`, `{{quantity}}`. No WYSIWYG. No arbitrary HTML beyond current M4 contract. Validate before save.

---

## 10. Product exclusions — server contract

Enhanced-select is convenience only.

All submitted IDs **must** be independently server-side:

1. normalized  
2. positive-integer validated  
3. deduplicated  
4. bounded to max **200**

Do not trust AJAX/UI validation. Missing/deleted IDs may remain until admin removes them. Parent/variation match semantics unchanged.

---

## 11. Page targeting

| Context | v1 policy |
|---------|-----------|
| checkout | **hard denied** (architecture) |
| cart | default denied; admin toggle `load_on_cart` |
| account | default denied; admin toggle `load_on_account` |
| admin / REST / CLI / feeds | hard denied (not admin toggles) |
| home / shop / product / search / content | remain eligible when not otherwise denied (no inventing unsupported page-type toggles) |

REST `page_context` remains `product|unknown` selection hint (not authz).

---

## 12. Diagnostics

### Execution scope

Evaluated **only** on the USP Social Proof admin/diagnostics surface.

Event diagnostic queries MUST NOT run on: storefront, notifications REST, capture, suppression, refunds, cleanup, unrelated wp-admin/Woo screens.

Do not persist diagnostic snapshots.

### Allowed event diagnostics (fixed set)

| Metric | Query style |
|--------|-------------|
| active count | aggregate `COUNT` by `status` |
| suppressed count | aggregate `COUNT` by `status` |
| newest active `occurred_at` | `MAX(occurred_at) WHERE status='active'` |
| oldest active `occurred_at` | `MIN(occurred_at) WHERE status='active'` |

Use existing indexes (`status_occurred`, etc.) where practical. Retention 7–90 days bounds table growth. **No** schema/index changes solely for diagnostics without stopping for review.

**Hard zeros:** `wc_get_product` = 0; `wc_get_order` = 0; no customer/order joins; no row materialization merely to count; no provenance.

**Capture-lag AVG:** **not** included in v1 required diagnostics.

### Cheap runtime / config health

USP version; `DB_VERSION`; WooCommerce active/version; HPOS status; UGC available/version/API; template validity; effective settings; Action Scheduler / cleanup health.

Forbidden in diagnostics: `source_order_id`, `source_item_id`, names, emails, addresses, IPs, hidden provenance.

---

## 13. Retention UX

Administrator configurable **7–90** days; default **60**. Server-side clamp. Changing retention does **not** trigger unbounded synchronous purge — next scheduled cleanup run applies the new cutoff.

---

## 14. Uninstall

Ship `uninstall.php` in M6. Removes all USP-owned data (ADR-0017):

- `usp_settings`, `usp_settings_version`
- legacy `usp_retention_days`, `usp_exclude_out_of_stock`
- `usp_db_version`, migrate lock
- `{prefix}usp_events`
- USP Action Scheduler cleanup jobs

No “retain data” toggle. Deactivation retains data; must not multiply schedules on reactivate.

---

## 15. Work packages

| WP | Scope | Tests / acceptance | Exclusions |
|----|-------|--------------------|------------|
| M6-WP1 | `usp_settings`, `usp_settings_version`, migration, wire policies | migration suite | schema change |
| M6-WP2 | Woo submenu, page shell, caps, nonces | unauthorized denied | top-level WP menu |
| M6-WP3 | display master + template UI + REST `200 []` | disable contract tests | 204; timing UI |
| M6-WP4 | products (server ID validation) + cart/account + geo | policy binding tests | region/city % |
| M6-WP5 | retention + privacy copy + uninstall.php | uninstall unit | delete-all-events UI |
| M6-WP6 | diagnostics aggregates | budget/privacy tests; no WC product/order | lag AVG; event browser |
| M6-WP7 | security/a11y; lift `src/Admin` CI forbid | PHPUnit | Playwright |
| M6-WP8 | DEV admin acceptance + internal closure | gate checklist | tag/release/publish/prod |

Packages: expect `src/Admin/` (+ settings/diagnostics helpers); touch existing policy classes for settings reads; `uninstall.php` at plugin root.

---

## 16. M6 acceptance scenarios

A. `manage_woocommerce` can access; others cannot.  
B. Defaults reproduce v0.5.0 runtime behavior.  
C. Valid settings save and affect runtime.  
D. Invalid settings reject safely.  
E. Template validation uses M4 grammar.  
F. Product exclusions use M4 policy + server ID contract.  
G. Geography toggle uses M5 policy.  
H. UGC unavailable diagnosed; not fatal.  
I. Diagnostics contain no forbidden PII/provenance.  
J. Retention bounds enforced.  
K. Reset defaults works; events retained.  
L. `display_enabled=false` → no enqueue; REST `200 []` + `no-store`; capture continues.  
M. Migration cases in §5 pass.  
N. Diagnostic event SQL only on USP admin surface.

---

## 17. Internal completion gate

See [M6-M7-V1-PROGRAM.md](M6-M7-V1-PROGRAM.md) §4. No public `v0.6.0`.

---

## Related

- [M6-M7-V1-PROGRAM.md](M6-M7-V1-PROGRAM.md)
- [M7-V1-HARDENING-RELEASE-PLAN.md](M7-V1-HARDENING-RELEASE-PLAN.md)
- ADR-0012, 0015, 0016, 0017
- FROZEN.md §12
