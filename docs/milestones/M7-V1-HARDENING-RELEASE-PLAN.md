# M7 — v1 Hardening and Release Plan (FROZEN)

> **Status: FROZEN / APPROVED FOR IMPLEMENTATION PLANNING**  
> **Planning review verdict:** READY TO FREEZE (post narrow correction 2026-09-07)  
> **Target published version:** **`1.0.0`**  
> **Baseline for planning:** `ce1aa0df0206516beb9625546eaa743c3f488578`  
> **M7 implementation baseline:** M6-complete `main` SHA (runtime `0.6.0`, no `v0.6.0` publish)  
> **DB_VERSION:** `20260829m1` (no settings-driven schema change)  
> **Program:** [M6-M7-V1-PROGRAM.md](M6-M7-V1-PROGRAM.md)

M7 is **not** a feature bucket. Purpose: turn complete M1–M6 into a
production-recommended **v1.0.0**.

---

## 1. Classification of remaining work

### ALREADY COMPLETE (do not re-implement)

- M1–M5 capture, storage, selection, REST, toaster, templates, targeting, UGC geo
- M5 real DEV reverse-proxy UGC gate
- Privacy exporter/eraser (HPOS-aware)
- Retention scheduler/purger
- Packaging ZIP + sha256; annotated release workflow; private update publish path
- Selection budgets; REST `Cache-Control: no-store`; public DTO allowlist

### REQUIRED FOR V1

- Release-blocker hardening found in M6/M7 audit (security, privacy, a11y blockers)
- Upgrade / clean-install / deactivate-reactivate acceptance
- Woo inactive + UGC absent/failing graceful behavior
- Cache/proxy complete-system regression (HTML/FPC + REST no-store + visitor variation on uncached REST)
- Browser/responsive smoke on representative surfaces
- Packaging + private update `v0.5.0 → v1.0.0` on DEV
- Support matrix freeze from CI at release time
- Annotated `v1.0.0` + GitHub Release + private publish + closure docs

### NICE TO HAVE / POST-V1

- Application rate limiter (M2 deferred; only if abuse evidence)
- Playwright / large E2E framework (not required; no existing USP Playwright)
- Capture-lag diagnostics
- Timing UI knobs
- Event browser

---

## 2. Work packages

| WP | Focus | Code changes |
|----|-------|--------------|
| M7-WP1 | Release-blocker code/security hardening from audit | **Only if blockers found** |
| M7-WP2 | Upgrade / clean-install / lifecycle acceptance | tests + evidence docs |
| M7-WP3 | Browser / a11y / cache-proxy acceptance | fix blockers only |
| M7-WP4 | Packaging + private-update `0.5.0→1.0.0` | release-state docs if needed |
| M7-WP5 | Complete DEV v1 acceptance document | docs + PASS evidence |
| M7-WP6 | v1.0.0 release-state, tag, publish, closure | version metadata + docs; **no auto prod** |

Do not invent code changes for a WP if testing shows none are required.

---

## 3. Security checklist (v1)

- Caps (`manage_woocommerce`); nonces; CSRF on admin mutations
- REST validation / allowlist; UUID validation; no anonymous writes
- Prepared SQL; no provenance in public/admin diagnostics
- Template escaping; admin output escaping; stored settings sanitized
- Dependency failures soft (Woo/UGC); no fatal; no error leakage of PII
- Update/package integrity (checksum workflow)
- Automated tests where practical for caps, disable REST contract, migration, diagnostics privacy

---

## 4. Privacy data-flow map (v1)

| Stage | Data |
|-------|------|
| SOURCE | WooCommerce order/item (purchase country, product, qty, provenance ids) |
| PERSISTED USP | `usp_events` fields per ADR-0003 (country-only; no city/region; no buyer identity; no visitor IP) |
| EPHEMERAL | visitor country from UGC during REST selection only |
| PUBLIC DTO | allowlisted notification fields only |
| ADMIN | settings + aggregate diagnostics (no order ids / emails / IPs) |
| ERASURE | HPOS-safe WC order lookup → hard-delete USP rows |
| RETENTION | `occurred_at`-based purge; 7–90 days |
| UNINSTALL | remove all USP-owned data (ADR-0017) |

---

## 5. Performance budgets (freeze measurable targets)

| Area | Budget |
|------|--------|
| Frontend assets | ~14KB JS + ~2KB CSS gzipped order of magnitude; no new heavy frameworks |
| REST | candidate SQL bounded by CandidateQuery constants; product resolves ≤20; K≤10; UGC resolve ≤1/request |
| Capture | idempotent per order/item; no unbounded scans |
| Cleanup | batched purge (existing batch size) |
| Admin diagnostics | fixed aggregate queries only; 0 `wc_get_product` / 0 `wc_get_order` |
| Forbidden | SQL `RAND()`; unbounded storefront scans; diagnostic event SQL outside USP admin |

---

## 6. Cache / proxy acceptance (DEV)

Prove:

- REST always `Cache-Control: no-store`
- No visitor-specific notification payload in shared FPC HTML
- Visitor-country variation occurs server-side on uncached REST when UGC available
- Cloudflare / SWAG path does not cache notifications responses
- `display_enabled=false` still returns `200 []` + `no-store`

---

## 7. Accessibility

Audit M3 toaster + M6 admin for v1 blockers: keyboard, focus, dismiss, ARIA/live region, reduced motion, contrast, labels/help/errors. Fix blockers; do not redesign for aesthetics.

---

## 8. Browser / responsive

Supported baseline from WP/CI conventions (Chromium desktop + mobile; Safari/WebKit if available). Smoke: PDP, shop/archive, home/content, search, excluded contexts. No huge matrix. **No Playwright required.**

---

## 9. WooCommerce / WP / PHP matrix

- Declared: WordPress ≥ 6.5, PHP ≥ 8.1 — claim only what CI tests
- HPOS enabled required; HPOS disabled if still claimed supported
- Variations, deleted/hidden products, OOS, refunds, cancel/fail paths
- Freeze tested-up-to values at release time from available environment

---

## 10. Upgrade acceptance

| Path | Verify |
|------|--------|
| `v0.5.0` → M6 (`0.6.0`) → `1.0.0` | events retained; settings migrate once; filters still override; schema idempotent; cleanup intact; no duplicate capture |
| Earlier M1 schema → v1 | migrator idempotent; no region/city columns |

---

## 11. Clean install acceptance

Activate → schema created → defaults correct → admin accessible → no PHP warnings → no FE console errors → first qualifying purchase captured → notification appears → privacy/cleanup registered.

---

## 12. Deactivate / reactivate

No corruption; events preserved; AS jobs do not multiply; capture idempotent; settings retained.

---

## 13. Dependency failure

| Case | Expectation |
|------|-------------|
| WooCommerce inactive | USP does not fatal |
| UGC absent / invalid / failing | USP functional globally |
| Re-enable deps | recovers without repair ritual |

---

## 14. Observability

No custom persistent logging required for v1. Prefer diagnostics. If Woo logger used: no customer/visitor PII; no logging of normal successful REST.

---

## 15. Test suite finalization

Inventory M0–M6 tests; close gaps for: admin/settings, migration, master-disable REST, diagnostics privacy/budget, security caps, upgrade, uninstall, packaging. Do not chase coverage %.

---

## 16. Packaging acceptance

Release ZIP contains only required runtime files; vendor production autoload; no tests/dev/secrets/DEV config/fixtures; correct plugin root + version metadata; deterministic-enough build + checksum.

---

## 17. Private update acceptance (DEV)

```text
installed v0.5.0 → published v1.0.0 offered → update installs
→ plugin active/healthy → data/settings preserved
```

No production deploy implied.

---

## 18. Release sequence

1. M7 PR merge + post-merge CI  
2. Full DEV v1 acceptance PASS  
3. Release-state preparation (runtime + Stable + CHANGELOG = `1.0.0`)  
4. Annotated `v1.0.0`  
5. GitHub Release + assets  
6. Private update publish  
7. Closure docs (do **not** retarget tag)  
8. Production deploy: **explicit / manual** later  

No `v0.6.0` public release.

---

## Related

- [M6-M7-V1-PROGRAM.md](M6-M7-V1-PROGRAM.md)
- [M6-ADMIN-DIAGNOSTICS-PLAN.md](M6-ADMIN-DIAGNOSTICS-PLAN.md)
- [RELEASE.md](../RELEASE.md)
- ADR-0013, 0015, 0016, 0017
