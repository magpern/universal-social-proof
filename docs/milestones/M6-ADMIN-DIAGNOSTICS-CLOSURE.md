# M6 — Admin UX and Diagnostics Closure

> **Status: M6 INTERNAL CLOSED**  
> **Runtime:** `0.6.0`  
> **Stable tag:** `0.5.0` (unchanged — no public `v0.6.0`)  
> **Latest published release:** `v0.5.0`  
> **DB_VERSION:** `20260829m1` (unchanged)  
> **Closure date:** 2026-09-07

## Baseline and delivery

| Item | Value |
|------|-------|
| Freeze baseline | `fd36f8a648d6b2660ac347dcccf1bb932f29a37e` |
| Freeze commit | `7ce88daf1fb4fb72552b425f61e6d8173a4890f2` (PR #10) |
| Implementation branch | `feature/m6-admin-diagnostics` |
| Implementation PR | https://github.com/magpern/universal-social-proof/pull/11 |
| Implementation merge SHA | `c2ac9b9fc4aae42cc8d730b2fa290b48f5200f63` |
| Post-merge CI | PASS (`34105741544`) |

## Delivered

- `usp_settings` + `usp_settings_version = 1` (ADR-0015)
- One-shot legacy migration from `usp_retention_days` / `usp_exclude_out_of_stock` (legacy rows retained inert)
- WooCommerce → Social Proof (`manage_woocommerce`)
- Display-only master switch: REST `200 []` + `Cache-Control: no-store`; capture continues
- Template / product exclusions / OOS / cart / account / geo / retention
- Diagnostics aggregates only (ADR-0016); no provenance/PII
- `uninstall.php` (ADR-0017)
- Unit + integration coverage; CI guards updated for M6

## Explicitly not done

- No `v0.6.0` Git tag
- No GitHub Release `v0.6.0`
- No private update publish for `0.6.0`
- No production deployment
- M7 **not started**

## DEV acceptance

PASS: menu/capability, settings save/validate/reset, display switch REST contract, diagnostics privacy, checkout hard-deny, UGC health reported, defaults restored after tests.

## Next

Begin **M7** from `M7_IMPLEMENTATION_BASELINE` (main SHA after this closure commit) under [M7-V1-HARDENING-RELEASE-PLAN.md](M7-V1-HARDENING-RELEASE-PLAN.md).
