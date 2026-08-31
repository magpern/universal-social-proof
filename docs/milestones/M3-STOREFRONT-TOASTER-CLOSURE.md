# M3 — Storefront Toaster Closure

**Status:** CLOSED  
**Verdict:** PASS  
**Version:** `0.3.0`  
**Release tag:** `v0.3.0` → `b0328d7505d6ec341de4ec1fd4b8c27e78ac2281`  
**PR:** https://github.com/magpern/universal-social-proof/pull/4 (merged)  
**Merge commit (`main`):** `b0328d7505d6ec341de4ec1fd4b8c27e78ac2281`  
**Baseline `main` (pre-M3):** `31757fe5d6fe4c6a8f7410370a8a8c707c1f46d2`  
**Plan freeze:** `0dda142af3041d8468be1b12dfc440d560435d1e`  
**Feature branch (pre-merge tip):** `c3602d35bd29003ac7387befd7f4c86fb76819cc`  
**Production:** untouched  
**M4:** not started  

## Commits

| Role | SHA | Subject |
|------|-----|---------|
| Plan freeze | `0dda142af3041d8468be1b12dfc440d560435d1e` | docs(m3): freeze storefront toaster plan |
| Frontend PHP | `8df0146` | feat(frontend): add M3 storefront bootstrap and shell |
| Toaster JS/CSS | `ab0fa70` | feat(toaster): add bounded vanilla presentation runtime |
| Tests | `57cd74b` | test(m3): cover toaster frontend and session behavior |
| CI | `d70141d` | ci(m3): enable frontend assets and JS validation |
| Docs / version | `abadd34e70d0b9ca1aafdb4c2ea4f2b55c3bcad2` | docs(m3): record version and implementation closure |
| SHA / validation table | `c3602d35bd29003ac7387befd7f4c86fb76819cc` | docs(m3): record implementation SHAs and validation |
| Merge to main | `b0328d7505d6ec341de4ec1fd4b8c27e78ac2281` | Merge pull request #4 from magpern/feature/m3-storefront-toaster |
| Tag | `v0.3.0` | Annotated tag on merge commit (`f1fd4b06249cc9db482b30184fef473f838d499e`) |

## Expected M3 behavior

**M3 v0.3.0 is intentionally infrastructure-complete but visually inert during normal operation against the M2 API because M2 does not expose `message`. This is expected M3 behavior, not an implementation defect.**

M4 activates normal visible production notifications by adding the server-rendered `message` field.

**Test/visual fixtures are test-only** under `tests/js`, `tests/fixtures`, `tests/visual`.  
No fixture data is injected through production plugin PHP, WordPress bootstrap configuration, storage, the database, or the public REST API.

## Delivered

| Area | Result |
|------|--------|
| PHP | `FrontendController`, `AssetLoader`, `BootstrapConfig`, `ShellRenderer` |
| JS | Single `assets/js/usp-toaster.js` (dual-env export) — 13286 bytes (≤16 KiB budget) |
| CSS | `assets/css/usp-toaster.css` — 2148 bytes |
| Display gate | `canPresent` requires non-empty `message` |
| Shown IDs | In-memory SoT; sessionStorage persistence; retain ≤100; wire exclude ≤20; client full-set filter |
| Inert M2 | Stop after first successful batch with valid non-presentable events |
| Enqueue | Skip admin, REST, WP-CLI, feeds, checkout, cart, account; load PDP/shop/ordinary storefront |
| Schema | `20260829m1` unchanged |

## PO decisions (resolved)

- PO-1 display-gated — accepted  
- PO-2 dismiss current only — accepted  
- PO-3 enqueue defaults (checkout architecture-aligned; cart/account M3 defaults) — accepted  
- PO-4 timing 3s/6s/2s/maxBatches 3 — accepted  

## Tests and local validation

| Gate | Result |
|------|--------|
| PHPCS | PASS |
| `scripts/ci/check.sh` | PASS |
| Unit | PASS — 33 tests (1 skipped), 157 assertions |
| Integration | PASS — 52 tests, 1708 assertions |
| Node JS | PASS — 18 tests |

## CI

| Run | SHA | Result |
|------|-----|--------|
| PR #4 | `c3602d35bd29003ac7387befd7f4c86fb76819cc` | SUCCESS — [33373956956](https://github.com/magpern/universal-social-proof/actions/runs/33373956956) |
| Post-merge `main` | `b0328d7505d6ec341de4ec1fd4b8c27e78ac2281` | SUCCESS — [33375584786](https://github.com/magpern/universal-social-proof/actions/runs/33375584786) |

Jobs: Lint/PHPCS/M3 policy + JS tests, unit PHP 8.1/8.3/8.4, integration PHP 8.3 / WC 11.0.1.

## Explicit absences

No Template/Geo/Admin packages; no token grammar; no production message generation; no PHP/REST/DB fixtures; no anonymous writes; no fake purchases.

## Release closure (post-merge)

1. PR #4 merged to `main` as `b0328d7505d6ec341de4ec1fd4b8c27e78ac2281`
2. Post-merge CI on that commit: SUCCESS
3. Annotated tag `v0.3.0` created on that merge commit and pushed
4. Tag verified: `git rev-parse v0.3.0^{}` = merge SHA; tag object `f1fd4b06249cc9db482b30184fef473f838d499e`
5. Header / `USP_VERSION` / CHANGELOG on the tagged commit all say `0.3.0`
6. M3 recorded **CLOSED**

Next milestone: **M4** (`0.4.0`) — not started in this release step.

## Working tree

Clean on `main` after this release-record commit (except as noted at commit time).
