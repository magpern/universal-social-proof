# Universal Social Proof — M3 Storefront Toaster Plan

**Status:** FROZEN / APPROVED  
**Baseline `main`:** `31757fe5d6fe4c6a8f7410370a8a8c707c1f46d2`  
**Target version:** `0.3.0`  
**Branch:** `feature/m3-storefront-toaster`  
**M2 release:** `v0.2.0` → `fe5fea7cc49e86eee62c3e0b3e144f84f6c59306`

This document is the authoritative M3 milestone specification underneath `docs/architecture/FROZEN.md`. Product Owner approved the plan with resolved PO-1…PO-4 and the fixture-boundary correction below. Do not redesign during implementation.

---

## 1. Objective

Deliver a small, production-quality **vanilla JS storefront toaster infrastructure** over the M2 anonymous notifications API: empty cache-safe shell, asset loading, REST client, DTO validation, display gating, queue/rotation, shown-ID tracking, relative time, DOM renderer, accessibility, motion, and responsive layout.

**M3 v0.3.0 is intentionally infrastructure-complete but visually inert during normal operation against the M2 API because M2 does not expose `message`. This is expected M3 behavior, not an implementation defect. M4 activates normal visible production notifications by adding the server-rendered `message` field.**

**Test/visual fixtures are test-only and may not be injected through production plugin PHP, WordPress bootstrap configuration, the database, sessionStorage/localStorage, or the public REST API.**

M3 visual acceptance uses a **client-side test/visual harness** with synthetic `message` fixtures. The real anonymous DEV storefront is separately verified to remain inert against the live M2 DTO. No fixture data is injected through production plugin PHP, WordPress bootstrap configuration, storage, the database, or the public REST API.

---

## 2. Resolved PO decisions

| ID | Decision | Resolution |
|----|----------|------------|
| **PO-1** | Display boundary | Display-gated: visible path requires non-empty `message`. Live M2 stays inert. No chrome-only toast. No invented purchase copy. |
| **PO-2** | Dismiss | Dismiss current only; mark shown; continue rotation. Not session-wide suppress. |
| **PO-3** | Enqueue exclusions | Checkout excluded (architecture-aligned). Cart/account excluded as **M3 presentation defaults** (not immutable architecture). Admin/feeds excluded. PDP/shop/ordinary storefront eligible. |
| **PO-4** | Timing | initial 3000 ms; visible 6000 ms; gap 2000 ms; maxBatches 3. Motion ~280 ms (0 under reduced motion). |

No remaining PO decisions.

---

## 3. Explicit fixture boundary (mandatory)

**Forbidden:**

- PHP fixture injection
- WordPress debug-event injection
- Capability-gated fake notifications
- Fixture DTOs in `wp_localize_script`
- Query-string switches that activate fake notifications
- Hidden admin/operator fake-event modes
- REST fixture endpoints or parameters
- Fake M1/M2 events / synthetic purchase records

**Allowed:** synthetic JS fixtures under `tests/` (e.g. `tests/js/`, `tests/fixtures/`, `tests/visual/`) for Node tests and isolated visual harness only. Not reachable by anonymous storefront runtime.

---

## 4. Frozen constraints

| Constraint | Source |
|------------|--------|
| Empty shell + config; events via REST `no-store` | FROZEN §10, ADR-0008 |
| M2 DTO: `public_id`, `product_url`, `thumbnail_url`, `occurred_at` | ADR-0011 amendment |
| `message` additive in M4; no client tokens; no English production stub | ADR-0011 |
| Vanilla JS; relative time from `occurred_at` only | FROZEN §11 |
| SessionStorage for shown ids / dismiss; `exclude` max 20 | FROZEN §11, §10 |
| No anonymous server writes | FROZEN §11 |
| No bounce; `prefers-reduced-motion`; polite live region | FROZEN §11 |
| `page_context` not authz; enqueue gates UX | ADR-0008 |
| Checkout excluded by default (enqueue) | FROZEN §15 / §10 |
| Schema unchanged `20260829m1` | M2 closure |
| Version `0.3.0` / tag after merge | ADR-0013 |

---

## 5. M2 contract (unchanged)

```text
GET {rest_url('universal-social-proof/v1/notifications')}
  ?limit=5
  &page_context=product|unknown
  &product_id=<PDP only>
  &exclude[]=<uuidv4>   # ≤20 most recent
```

Do not hard-code `/wp-json/`. Do not add `message` to the API in M3.

---

## 6. Display gate

```text
canPresent(event) ⇔ typeof message === 'string' && trim(message) !== ''
```

- Valid M2 DTO without `message` → structurally valid, **not presentable**, never shown
- Fixture / future M4 event with message → presentable
- Do not require `message` as an M2 REST field

### Inert-M2 refetch rule

If a successfully parsed batch contains valid M2 events but **zero** presentable events because `message` is absent/empty, **stop after that first successful request** (no 2nd/3rd pointless refetch). `maxBatches=3` applies to the presentable / fixture path.

---

## 7. Architecture summary

### PHP package

```text
src/Frontend/FrontendController.php  — hooks
src/Frontend/AssetLoader.php         — should-load, enqueue, localize
src/Frontend/BootstrapConfig.php     — restUrl, limit, pageContext, productId, i18n, timing, maxBatches, storageKey
src/Frontend/ShellRenderer.php       — empty shell
```

### JS / CSS

```text
assets/js/usp-toaster.js   — SINGLE source of truth (dual-env export OK)
assets/css/usp-toaster.css
```

No bundler, concat script, mirrored `.mjs` implementation, jQuery, or framework.

### Bootstrap config

PDP: `pageContext=product`, `productId=<queried WC product>`.  
Else: `pageContext=unknown`, `productId=null`.  
No scraped DOM IDs. No selected events in config. PDP `productId` is page/URL-specific (FPC-safe for that URL).

### Enqueue gate (M3 defaults)

Exclude: admin, checkout, cart, account, feeds, non-HTML contexts.  
Eligible: PDP, shop/archive, ordinary storefront.  
Checkout = architecture-aligned. Cart/account = M3 presentation defaults.

### Shell

One inert/hidden shell in `wp_footer`. No event payload. JS-off → invisible. Sibling dismiss button; product `<a>` wraps content.

### Shown-ID model

- **In-memory collection is runtime SoT** for the page lifetime
- `sessionStorage` seeds and persists best-effort (`usp.v1.shown`)
- Displayed IDs enter memory even if storage fails
- Retain ≤100 UUIDs; send ≤20 most recent as `exclude`
- **Client-filter every returned `public_id` against the full retained set** before queueing
- REST exclude is an optimization; client owns session no-repeat

### Timing

```text
initialDelayMs = 3000
visibleMs      = 6000
gapMs          = 2000
maxBatches     = 3
motionMs       ≈ 280 (0 if reduced motion)
```

No `wp_options`. Max one visible toast. No stack. No unbounded polling.

### Relative time

`<45s` just now; `<60m` minutes; `<24h` hours; `<30d` days. Invalid/skew → drop. Localized via bootstrap i18n.

### A11y / motion

`role=status`, `aria-live=polite`, `aria-atomic=true`. No focus steal. No assertive spam. Opacity/transform only; honor `prefers-reduced-motion`.

### Performance

JS ≤12 KiB raw; CSS ≤6 KiB raw; 0 runtime deps; ≤3 REST batches; inert M2 → stop after 1 successful request.

---

## 8. Testing

- **PHP unit/integration:** bootstrap, gates, shell, PDP/non-PDP, no DTO in HTML, schema unchanged, no M4+ packages
- **Node JS:** same `usp-toaster.js`; canPresent; shown memory; storage fail; exclude 20 + full-set filter; inert M2 stop-after-one; fixture presentable path; dismiss; relative time; URL validation
- **Visual harness:** isolated under `tests/`; synthetic message fixtures; viewports 360/390/430/768/1440
- **Anonymous live:** inert; one request; no fake message; no HTML payload
- No Playwright required

---

## 9. CI

Allow: `src/Frontend/`, `assets/js|css/`, enqueue APIs, sessionStorage in assets JS.  
Forbid: Template/Geo/Admin, tokens, GeoContextAdapter, fake purchase, schema bump, anonymous writes.  
Add: JS tests, size budgets. Version asserts → `0.3.0`.

---

## 10. Version / release

Implementation identity `0.3.0` on the feature branch. Tag `v0.3.0` **only after** merge to `main` and post-merge CI (separate closeout). No GitHub Release object required.

---

## 11. Explicit exclusions

No M4 message generation/templates/tokens; no M5 UGC/geo; no M6 admin; no region/city; no fake events; no PHP/REST/DB fixtures; no anonymous writes; no schema changes; no production deploy.

---

## 12. Implementation sequence

1. This freeze commit  
2. Frontend PHP + Plugin  
3. Shell + CSS + single JS runtime  
4. Tests + harness  
5. CI + version + closure (PR open, not CLOSED)  
6. PR → CI (do not merge/tag in implementation pass)

---

## 13. Stop conditions

Stop if evidence requires changing the M2 public contract, production copy, fake-event infrastructure, schema migration, production modification, or M4 implementation.

---

## 14. Freeze readiness

**FROZEN.** Proceed to implementation on `feature/m3-storefront-toaster`.
