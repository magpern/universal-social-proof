# M2 — Selection + REST Closure

**Status:** Implementation complete; **PR open for PO review** (not merged, not tagged)  
**Verdict:** PASS (local validation)  
**Version:** `0.2.0` (header / `USP_VERSION` / CHANGELOG; **tag `v0.2.0` not created**)  
**Production:** untouched  

## Commits

| Role | SHA | Subject |
|------|-----|---------|
| Plan freeze | `2bcb180ed8016c9f412831df2b1724ed45921d15` | docs(m2): freeze M2 selection and REST plan |
| Candidate reader | `017ccc2` | feat(selection): add bounded candidate reader |
| Product resolver | `72d86ce` | feat(product): add public product resolver and budget |
| Selection engine | `7214120` | feat(selection): add selection engine and PDP preference |
| REST | `792f19d` | feat(rest): add anonymous notifications read API |
| Tests / CI | `d24cb2d` | test(m2): add selection and REST coverage |
| Docs / version | *(this commit)* | docs(m2): record M2 ADRs, version 0.2.0, and closure |

Starting `origin/main`: `1587032c271825fc47e1a230896edff03f0705b5`  
Branch: `feature/m2-selection-rest`

## Verdict summary

M2 delivers a bounded, privacy-safe public notifications read API on top of M1 storage. Preferred and global candidate pools stay separate. Product resolution is hard-capped at 20 USP-initiated `wc_get_product()` calls. The public DTO has no `message` (M4 adds it additively). Storefront toaster, templates, UGC, and admin UI remain absent.

## Schema and migration

| Item | Result |
|------|--------|
| Schema id | `usp_db_version` = `20260829m1` (**unchanged**) |
| New tables/indexes | None |
| Candidate SQL | `status = active AND occurred_at >= cutoff` + optional `product_id` / `NOT IN` exclusions; `ORDER BY occurred_at DESC LIMIT 80\|20` |

## Selection

| Item | Result |
|------|--------|
| Global cap | 80 |
| PDP preferred cap | 20 |
| Product-resolution budget | 20 (memoized repeats do not increment) |
| PDP search cap | 5 uncached preferred candidate loads |
| Pools | Separate shuffle + walk; global cannot consume PDP search budget first |
| Duplicate products | Allowed |
| Unique events | `public_id` unique in a response |
| OOS | Default OFF (`usp_exclude_out_of_stock`) |
| Variation | Live ineligible → skip; unresolvable → eligible parent fallback |

## REST

| Item | Result |
|------|--------|
| Route | `GET /wp-json/universal-social-proof/v1/notifications` |
| Auth | Anonymous; `__return_true`; no nonce; no writes |
| DTO | `public_id`, `product_url`, `thumbnail_url`, `occurred_at` |
| `message` | **Omitted** (PO-approved; ADR-0011 amendment) |
| Cache | `Cache-Control: no-store` on this route (200 and 400/405) |
| Empty | `200 []` |

## Tests and local validation

Host has no system PHP; gates ran in Docker (`ugeo-php8.3-mysqli` + MariaDB 11.4).

| Gate | Result |
|------|--------|
| `php vendor/bin/phpcs` | PASS |
| `scripts/ci/check.sh` | PASS |
| Unit | PASS — 23 tests, 84 assertions |
| Integration | PASS — 42 tests, 1636 assertions (PHP 8.3 / WC 11.0.1) |

## Release status

- **PR:** open, unmerged  
- **Tag:** none (`v0.2.0` is created only after approved merge to `main`)  
- **GitHub release:** none  
- **M3:** not started  
- **Production:** untouched  

Final release closure happens after approved merge/tag.
