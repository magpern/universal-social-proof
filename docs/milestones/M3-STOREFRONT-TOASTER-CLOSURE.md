# M3 — Storefront Toaster Closure

**Status:** Implementation complete; **PR open** (not merged, not tagged)  
**Verdict:** PASS (local validation pending push/CI)  
**Version:** `0.3.0` (header / `USP_VERSION` / CHANGELOG; **tag `v0.3.0` not created**)  
**Production:** untouched  
**M4:** not started  

## Baseline and freeze

| Item | Value |
|------|--------|
| Starting `main` | `31757fe5d6fe4c6a8f7410370a8a8c707c1f46d2` |
| Plan freeze | *`0dda142af3041d8468be1b12dfc440d560435d1e`* |
| Branch | `feature/m3-storefront-toaster` |
| M2 | CLOSED at `v0.2.0` |

## Expected M3 behavior

**M3 v0.3.0 is intentionally infrastructure-complete but visually inert during normal operation against the M2 API because M2 does not expose `message`. This is expected M3 behavior, not an implementation defect.**

M4 activates normal visible production notifications by adding the server-rendered `message` field.

**Test/visual fixtures are test-only** under `tests/js`, `tests/fixtures`, `tests/visual`.  
No fixture data is injected through production plugin PHP, WordPress bootstrap configuration, storage, the database, or the public REST API.

## Delivered

| Area | Result |
|------|--------|
| PHP | `FrontendController`, `AssetLoader`, `BootstrapConfig`, `ShellRenderer` |
| JS | Single `assets/js/usp-toaster.js` (dual-env export) |
| CSS | `assets/css/usp-toaster.css` |
| Display gate | `canPresent` requires non-empty `message` |
| Shown IDs | In-memory SoT; sessionStorage persistence; retain ≤100; wire exclude ≤20; client full-set filter |
| Inert M2 | Stop after first successful batch with valid non-presentable events |
| Enqueue | Skip admin, checkout, cart, account, feeds; load PDP/shop/ordinary storefront |
| Schema | `20260829m1` unchanged |

## PO decisions (resolved)

- PO-1 display-gated — accepted  
- PO-2 dismiss current only — accepted  
- PO-3 enqueue defaults (checkout architecture-aligned; cart/account M3 defaults) — accepted  
- PO-4 timing 3s/6s/2s/maxBatches 3 — accepted  

## Explicit absences

No Template/Geo/Admin packages; no token grammar; no production message generation; no PHP/REST/DB fixtures; no anonymous writes; no fake purchases.

## Release status

- **PR:** open, unmerged (fill URL after open)  
- **Tag:** none  
- **M4:** not started  
- **Production:** untouched  

Final CLOSED status happens after approved merge/tag.
