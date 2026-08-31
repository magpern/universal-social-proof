# M4 — Templates and Targeting Closure

> **Status:** Implementation complete on feature branch; **PR open / not merged**; **not tagged**; production untouched.  
> **Do not mark M4 CLOSED** until merge + `v0.4.0` closeout.

| Item | Value |
|------|-------|
| Target version | `0.4.0` (on branch; tag deferred) |
| Feature branch | `feature/m4-templates-targeting` |
| Baseline `main` | `f6075b2ed632cce28effe0ec679cfea64e4bed96` |
| Freeze commit | recorded in PR / git log (`docs(m4): freeze…`) |
| Schema | `usp_db_version = 20260829m1` (**unchanged**) |
| M5 / M6 | **not started** |
| Production | **untouched** |
| Tag `v0.4.0` | **not created** |

## Delivered

- ADR-0011 / FROZEN §11 amendment: `message` + `show_relative_time`; `{{location}}` purchase-country alias.
- Frozen plan: [M4-TEMPLATES-TARGETING-PLAN.md](M4-TEMPLATES-TARGETING-PLAN.md)
- `src/Template` + `src/Targeting`
- Selection-level `ProductTargetingPolicy` (not in `PublicProductResolver`)
- Post-selection render; omit on failure; **no refill**
- Narrow M3 chrome gate for `show_relative_time === false`
- Filter-only config (`usp_notification_template`, `usp_excluded_product_ids`); **no M4 options**

## Explicitly absent

Geo/UGC visitor weighting; Admin UI; persisted template/exclusion options; fake purchases; schema migration; product-name snapshots; client template engine.
