# M3 — Storefront Toaster Closure

**Status:** Implementation complete; **PR open** (not merged, not tagged)  
**Verdict:** PASS (local validation)  
**Version:** `0.3.0` (header / `USP_VERSION` / CHANGELOG; **tag `v0.3.0` not created**)  
**Production:** untouched  
**M4:** not started  

## Baseline and freeze

| Item | Value |
|------|--------|
| Starting `main` | `31757fe5d6fe4c6a8f7410370a8a8c707c1f46d2` |
| Plan freeze | `0dda142af3041d8468be1b12dfc440d560435d1e` |
| Branch | `feature/m3-storefront-toaster` |
| Feature HEAD | `abadd34e70d0b9ca1aafdb4c2ea4f2b55c3bcad2` |
| M2 | CLOSED at `v0.2.0` |

### Implementation commits

| Role | SHA | Subject |
|------|-----|---------|
| Freeze | `0dda142af3041d8468be1b12dfc440d560435d1e` | docs(m3): freeze storefront toaster plan |
| Frontend PHP | `8df0146` | feat(frontend): add M3 storefront bootstrap and shell |
| Toaster JS/CSS | `ab0fa70` | feat(toaster): add bounded vanilla presentation runtime |
| Tests | `57cd74b` | test(m3): cover toaster frontend and session behavior |
| CI | `d70141d` | ci(m3): enable frontend assets and JS validation |
| Docs/version | `abadd34e70d0b9ca1aafdb4c2ea4f2b55c3bcad2` | docs(m3): record version and implementation closure |

## Expected M3 behavior

**M3 v0.3.0 is intentionally infrastructure-complete but visually inert during normal operation against the M2 API because M2 does not expose `message`. This is expected M3 behavior, not an implementation defect.**

M4 activates normal visible production notifications by adding the server-rendered `message` field.

**Test/visual fixtures are test-only** under `tests/js`, `tests/fixtures`, `tests/visual`.  
No fixture data is injected through production plugin PHP, WordPress bootstrap configuration, storage, the database, or the public REST API.

## Delivered

| Area | Result |
|------|--------|
| PHP | `FrontendController`, `AssetLoader`, `BootstrapConfig`, `ShellRenderer` |
| JS | Single `assets/js/usp-toaster.js` (dual-env export) — 13286 bytes |
| CSS | `assets/css/usp-toaster.css` — 2148 bytes |
| Display gate | `canPresent` requires non-empty `message` |
| Shown IDs | In-memory SoT; sessionStorage persistence; retain ≤100; wire exclude ≤20; client full-set filter |
| Inert M2 | Stop after first successful batch with valid non-presentable events |
| Enqueue | Skip admin, checkout, cart, account, feeds; load PDP/shop/ordinary storefront |
| Schema | `20260829m1` unchanged |

## Local validation

| Gate | Result |
|------|--------|
| PHPCS | PASS |
| `scripts/ci/check.sh` | PASS |
| Unit | PASS — 33 tests (1 skipped), 157 assertions |
| Integration | PASS — 52 tests, 1708 assertions |
| Node JS | PASS — 18 tests |

## PO decisions (resolved)

- PO-1 display-gated — accepted  
- PO-2 dismiss current only — accepted  
- PO-3 enqueue defaults (checkout architecture-aligned; cart/account M3 defaults) — accepted  
- PO-4 timing 3s/6s/2s/maxBatches 3 — accepted  

## Explicit absences

No Template/Geo/Admin packages; no token grammar; no production message generation; no PHP/REST/DB fixtures; no anonymous writes; no fake purchases.

## Release status

- **PR:** open, unmerged  
- **Tag:** none  
- **M4:** not started  
- **Production:** untouched  

Final CLOSED status happens after approved merge/tag.
