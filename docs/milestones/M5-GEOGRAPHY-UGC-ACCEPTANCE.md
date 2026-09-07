# M5 — Geography / UGC acceptance evidence

**Branch:** `feature/m5-geography-ugc`  
**Freeze baseline:** `1d3c959372b97168b38d077e319a427308110d96` (PR #7)  
**Runtime version:** `0.5.0`  
**Latest published release:** `v0.4.1`  
**WordPress Stable tag:** `0.4.1` (advances only when `v0.5.0` is released)  
**DB_VERSION:** `20260829m1` (unchanged)

## Automated

| Suite | Result |
|-------|--------|
| Unit (`phpunit.xml.dist`) | PASS — 73 tests (1 skipped) |
| Integration (`phpunit-integration.xml.dist`) | PASS — 74 tests / 2092 assertions |
| JS (`node --test`) | PASS — 22 tests |
| PHPCS | PASS |
| CI scope (`scripts/ci/check.sh` subset) | PASS (Geo present, Admin absent, schema pinned) |
| GitHub Actions (PR #8) | PASS — https://github.com/magpern/universal-social-proof/actions/runs/34066077423 |

## DEV scenarios (fixture / adapter-driven)

| ID | Scenario | Result |
|----|----------|--------|
| A | Same-country preference (visitor SE) | PASS — integration `test_non_pdp_country_preference_and_sparse_fallback` |
| B | No matching country → global fallback | PASS — same test (visitor JP) |
| C | UGC unavailable / null / malformed / Throwable | PASS — REST + Null adapter; no 5xx |
| D | PDP Tier1–4 precedence | PASS — Tier1 accept / Tier2 / Tier3 / Tier4 tests |
| E | `{{country}}` = purchase (DE), not visitor (SE) | PASS — `test_template_purchase_country_not_visitor` |
| F | Public DTO unchanged (no visitor_country) | PASS — allowlist + REST assertions |
| G | Worst-case budgets (SQL ≤4, rows ≤200, K ≤10) | PASS — `test_worst_case_pdp_geo_sql_and_row_budgets` |

## Proxy / real UGC path

**DEFERRED** for implementation-PR merge.

Controlled fixture/adapter acceptance covers soft dependency and normalization. Live reverse-proxy UGC resolution on DEV was not exercised in this implementation gate.

**Release gate (frozen):**

- Implementation PR #8 **may merge** with proxy/real-UGC acceptance still **DEFERRED**.
- Annotated tag / GitHub / private **`v0.5.0` MUST NOT** be created until real DEV reverse-proxy UGC acceptance has **PASSED** (ADR-0002).

Intended sequence: merge → post-merge CI → real proxy UGC acceptance → release-state update (including `Stable tag: 0.5.0`) → annotated `v0.5.0` → publish → closure.

## Notes

- Visitor country is request-local only; never persisted.
- Client `?country=` is ignored (not a registered REST arg).
- Shared `PDP_SEARCH_CAP=5` wraps Tier1+Tier2; `ProductResolutionBudget::MAX=20` is request-global.
- No schema migration; no `src/Admin/`.
