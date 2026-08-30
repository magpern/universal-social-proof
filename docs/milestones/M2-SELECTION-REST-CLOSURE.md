# M2 — Selection + REST Closure

**Status:** CLOSED  
**Verdict:** PASS  
**Version:** `0.2.0`  
**Release tag:** `v0.2.0` → `fe5fea7cc49e86eee62c3e0b3e144f84f6c59306`  
**PR:** https://github.com/magpern/universal-social-proof/pull/3 (merged)  
**Merge commit (`main`):** `fe5fea7cc49e86eee62c3e0b3e144f84f6c59306`  
**Baseline `main` (pre-M2):** `1587032c271825fc47e1a230896edff03f0705b5`  
**Plan freeze:** `2bcb180ed8016c9f412831df2b1724ed45921d15`  
**Feature branch (pre-merge tip):** `f1a01d4d5f1f8eefa996adf8f383566d458f2428`  
**Production:** untouched  

## Commits

| Role | SHA | Subject |
|------|-----|---------|
| Plan freeze | `2bcb180ed8016c9f412831df2b1724ed45921d15` | docs(m2): freeze M2 selection and REST plan |
| Candidate reader | `017ccc2b0cb6169b8489a7ca09e623593508f03a` | feat(selection): add bounded candidate reader |
| Product resolver | `72d86cec0d132f10eea90bdd6edf77bd0caf63fa` | feat(product): add public product resolver and budget |
| Selection engine | `72141206ba7b7d128d05c3fa3840039c99e7cbce` | feat(selection): add selection engine and PDP preference |
| REST | `792f19d1bd435a62e5c59c1d1a5827a30b2bc87c` | feat(rest): add anonymous notifications read API |
| Tests / CI | `d24cb2d5546a0dd94fc1105e2ba64df79f00a67b` | test(m2): add selection and REST coverage |
| Docs / version | `84cb7dbdcd37bd4e65698c5bf066fe25aad53e9d` | docs(m2): record M2 ADRs, version 0.2.0, and closure |
| SHA table | `2da0d15439d6e2d7e8a83d8ac373bf2201954c5a` | docs(m2): record M2 implementation commit SHAs |
| Finding A fix | `fcabd6cefd739867417cfa307797277a8518525c` | fix(selection): enforce hard PDP resolution cap |
| Finding B fix + tests | `944cd87f8c77db53fec1da558b42cd9ad37a8e64` | fix(rest): scope no-store to exact notifications route |
| Remediation docs | `da4091d40e1063553724840259774afbf8469eb9` | docs(m2): record PR review remediation |
| Remediation SHA table | `f1a01d4d5f1f8eefa996adf8f383566d458f2428` | docs(m2): record remediation documentation SHA |
| Merge to main | `fe5fea7cc49e86eee62c3e0b3e144f84f6c59306` | Merge pull request #3 from magpern/feature/m2-selection-rest |
| Tag | `v0.2.0` | Annotated tag on merge commit (`1c6dab2079ad13f749eeb6b2dfb4bb289706124d`) |

Starting `origin/main` (pre-M2): `1587032c271825fc47e1a230896edff03f0705b5`  
Branch: `feature/m2-selection-rest` (deleted after merge)  
Pre-remediation PR head: `2da0d15439d6e2d7e8a83d8ac373bf2201954c5a`

## PR #3 review remediation (2026-08-31)

Two review findings were confirmed against head `2da0d15` and fixed without reopening frozen M2 constants.

### Finding A — PDP preferred-search budget of 5

**Confirmed.** The engine counted uncached loads **after** each preferred candidate (`used_after - used_before`) and allowed the next candidate while `pdp_uncached < 5`. One candidate can require two uncached resolutions (historical variation, then parent fallback). With 4 preferred loads already consumed, that pair could initiate preferred resolutions #5 and **#6**. The global cap of 20 does not authorize exceeding the frozen PDP preferred-search cap of 5.

**Fix.** `ProductResolutionBudget` gained a nested additional cap. During preferred search the engine calls `begin_additional_cap(PDP_SEARCH_CAP)` so `try_consume()` uses `min(global remaining, phase remaining)`. Memoized IDs still skip `try_consume()`. The phase ends before leftover/global fill, which continues to use the global 20 only. Parent fallback is unchanged: live ineligible variation → skip; unresolvable variation → parent **when the effective budget still allows an uncached load** (cached parent may still be used).

**Regression.**

| Case | Result |
|------|--------|
| 4 preferred uncached + unresolved variation + uncached parent | variation consumes #5; parent is **not** initiated; preferred uncached = 5 |
| 4 preferred uncached + unresolved variation + memoized parent | variation consumes #5; cached parent is used; no sixth `wc_get_product()`; preferred uncached = 5 |
| Global budget | USP-initiated loads ≤ 20 |
| Existing PDP ≤5 + leftover global fill | retained |

### Finding B — exact REST cache-header route

**Confirmed.** `is_notifications_route()` exact-matched then fell back to `strpos( $route, '/' . NAMESPACE . ROUTE )`, so `/universal-social-proof/v1/notifications-other` was treated as the notifications endpoint.

**Fix.** Exact comparison only: `'/' . NAMESPACE . ROUTE === $route` (`/universal-social-proof/v1/notifications`).

**Regression.** Exact USP route → `no-store`. `notifications-other` and `/some-other/v1/notifications` are not modified. Existing 200/400/405 and unrelated-route tests retained. Tests apply `rest_post_dispatch` as `WP_REST_Server::serve_request()` does; `dispatch()` itself does not run that filter.

The frozen plan was not rewritten to match the old implementation.

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
| PDP search cap | 5 uncached preferred resolutions, enforced at consume-time via a phase ceiling (`min(global remaining, 5 remaining)`); a single candidate cannot initiate a sixth load |
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
| Cache | `Cache-Control: no-store` on the **exact** route `/universal-social-proof/v1/notifications` (200 and USP-generated 400/405). Prefix lookalikes such as `notifications-other` are not this endpoint. |
| Empty | `200 []` |

## Tests and local validation

Host has no system PHP; gates ran in Docker (`ugeo-php8.3-mysqli` + MariaDB 11.4).

| Gate | Result |
|------|--------|
| `php vendor/bin/phpcs` | PASS |
| `scripts/ci/check.sh` | PASS |
| Unit | PASS — 27 tests, 129 assertions |
| Integration | PASS — 45 tests, 1678 assertions (PHP 8.3 / WC 11.0.1) |

## CI

| Run | SHA | Result |
|------|-----|--------|
| PR #3 remediation | `f1a01d4d5f1f8eefa996adf8f383566d458f2428` | SUCCESS — [33339029507](https://github.com/magpern/universal-social-proof/actions/runs/33339029507) |
| Post-merge `main` | `fe5fea7cc49e86eee62c3e0b3e144f84f6c59306` | SUCCESS — [33340464462](https://github.com/magpern/universal-social-proof/actions/runs/33340464462) |

Jobs: Lint/PHPCS, unit PHP 8.1/8.3/8.4, integration PHP 8.3 / WC 11.0.1.

## Release closure (post-merge)

1. PR #3 merged to `main` as `fe5fea7cc49e86eee62c3e0b3e144f84f6c59306`
2. Post-merge CI on that commit: SUCCESS
3. Annotated tag `v0.2.0` created on that merge commit and pushed
4. Tag verified: `git rev-parse v0.2.0^{}` = merge SHA; tag object `1c6dab2079ad13f749eeb6b2dfb4bb289706124d`
5. Header / `USP_VERSION` / CHANGELOG on the tagged commit all say `0.2.0`
6. M2 recorded **CLOSED**

Next milestone: **M3** (`0.3.0`) — not started in this release step.

## Working tree

Clean on `main` after this release-record commit (except as noted at commit time).
