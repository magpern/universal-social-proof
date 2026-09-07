# M7 — Candidate acceptance evidence (pre-merge)

> **Status:** PASS (candidate; Stable still `0.5.0`)  
> **Baseline:** `08b529a8139bc3682f8790590f9ae8858d2047b4`  
> **Branch:** `feature/m7-v1-hardening`  
> **Runtime:** `1.0.0`  
> **Stable tag:** `0.5.0` (until release-state)  
> **DB_VERSION:** `20260829m1` (unchanged)

## Hardening delivered

| Area | Change |
|------|--------|
| Template | Token values → plain text (`html_entity_decode` + `wp_strip_all_tags`) |
| A11y | Shell dismiss `aria-label` |
| Security | `DiagnosticsService::collect()` requires `manage_woocommerce` |
| Privacy | Logger context allowlist |
| Version | Runtime `1.0.0`; Stable lag intentional |

## Automated (local)

- `composer ci` — PASS
- `composer phpcs` — PASS
- `composer test:unit` — PASS (91)
- `composer test:js` — PASS (22)
- Package `universal-social-proof-1.0.0.zip` — PASS (Stable lag allowed)

## Real DEV proxy (`https://dev.biopentra.eu`)

| Check | Result |
|-------|--------|
| Plugin version | `1.0.0` |
| `usp_db_version` | `20260829m1` |
| Storefront HTML | toaster shell + dismiss accessible name present |
| Storefront load | home `200` |
| `GET /wp-json/universal-social-proof/v1/notifications` | `200` + `Cache-Control: no-store`; CF `DYNAMIC` / `x-bp-cache: BYPASS` |
| Public DTO | allowlisted keys only; no visitor/provenance fields |
| `display_enabled=false` | `200 []` + `no-store`; re-enable restores |
| UGC | `universal_geo_get_country_code` available |

## Explicit non-goals on this branch

- Stable tag remains `0.5.0`
- No `v0.6.0` / no `v1.0.0` tag yet
- Production untouched
