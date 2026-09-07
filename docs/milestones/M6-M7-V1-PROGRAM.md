# M6 / M7 — Combined v1 Program

> **Status: FROZEN**  
> **Planning review verdict:** READY TO FREEZE (post narrow correction 2026-09-07)  
> **Baseline `main`:** `ce1aa0df0206516beb9625546eaa743c3f488578`  
> **Latest published release:** `v0.5.0` (peeled `495b12da46b2af3f292114b50c0ca58358e27abd`)  
> **Runtime / Stable / DB:** `0.5.0` / `0.5.0` / `20260829m1`  
> **Next published release:** **`v1.0.0`** (no required public `v0.6.0`)

This document owns the **combined program**: sequence, gates, version lifecycle,
and handoff. Detailed work packages live in:

- [M6-ADMIN-DIAGNOSTICS-PLAN.md](M6-ADMIN-DIAGNOSTICS-PLAN.md)
- [M7-V1-HARDENING-RELEASE-PLAN.md](M7-V1-HARDENING-RELEASE-PLAN.md)

---

## 1. Objective

Turn the complete M1–M5 storefront runtime into a production-recommended
**v1.0.0** by:

1. **M6** — WooCommerce admin + diagnostics so operators can run USP safely.
2. **M7** — hardening, full acceptance, packaging/update proof, release closure.

Do not invent work merely because two milestone numbers remain. M1–M5 already
shipped capture through geo-weighted display.

---

## 2. Sequence

```text
v0.5.0 published
    ↓
combined M6/M7 architecture freeze (this program) — docs only
    ↓
M6 implementation PR (runtime 0.6.0)
    ↓
M6 internal acceptance gate (NO v0.6.0 tag / Release / private publish)
    ↓
M7 hardening / complete-system acceptance PR (runtime → 1.0.0)
    ↓
annotated v1.0.0 + GitHub Release + private update publish
    ↓
production deploy remains explicit / manual
```

**Implementation strategy:** separate M6 PR then M7 PR (clean review boundary).

---

## 3. No public v0.6.0

| Action | M6 | M7 / v1.0.0 |
|--------|----|-------------|
| Runtime `USP_VERSION` | `0.6.0` | `1.0.0` |
| `readme.txt` Stable tag | stays `0.5.0` until v1 release PR | → `1.0.0` |
| Annotated Git tag | **none** | `v1.0.0` |
| GitHub Release | **none** | yes |
| Private update publish | **none** | yes |
| Production WordPress deploy | **none** | explicit / manual after publish |

Packaging already allows Stable lag (`scripts/build-release-package.sh`).
ADR-0013 amended: milestone *runtime* versions remain; *published* M6 tag may
be skipped when this program designates the next publish as `v1.0.0`.

---

## 4. M6 internal completion gate

M6 is complete when **all** of:

- M6 implementation PR merged to `main`
- post-merge CI green
- DEV admin acceptance PASS (see M6 plan)
- runtime version `0.6.0` on `main`
- **no** `v0.6.0` tag, GitHub Release, private publish, or production deploy

Then M7 starts from that `main` SHA.

---

## 5. M6 → M7 handoff baseline

Handoff artifacts:

- merged M6 SHA
- settings option `usp_settings` + `usp_settings_version = 1`
- `DB_VERSION` still `20260829m1`
- admin under WooCommerce (`manage_woocommerce`)
- diagnostics privacy/query budget per ADR-0016
- uninstall.php policy per ADR-0017
- M6 acceptance evidence (DEV)

---

## 6. v1.0.0 release gate

Before tagging `v1.0.0`:

- M7 PR merged; post-merge CI green
- Complete DEV v1 acceptance PASS (M7 plan)
- header / `USP_VERSION` / CHANGELOG / Stable tag all `1.0.0`
- package build + checksum PASS
- private update path `0.5.0 → 1.0.0` proven on DEV
- annotated tag only; never rewrite published tags
- production deploy **not** automatic

---

## 7. Schema policy

`usp_events` / `usp_db_version` / `DB_VERSION = 20260829m1` **unchanged** for
M6 settings work. Settings use WordPress options (`usp_settings`,
`usp_settings_version`) — not a table migration.

---

## 8. Hard exclusions (program-wide)

Fake purchases, city/region, buyer identity, visitor IP storage, analytics /
attribution / A-B, impression/click DBs, SaaS, page builders, visual HTML
template builder, WebSockets, REST writes, provenance event browser, multisite
network admin, localization management UI, elaborate role editor, WP-CLI
feature suite, GraphQL, recommendation engine.

---

## Related

- [architecture/FROZEN.md](../architecture/FROZEN.md)
- [adr/0013-version-release-policy.md](../adr/0013-version-release-policy.md)
- [adr/0015-settings-storage-precedence.md](../adr/0015-settings-storage-precedence.md)
- [adr/0016-diagnostics-privacy-budget.md](../adr/0016-diagnostics-privacy-budget.md)
- [adr/0017-uninstall-data-ownership.md](../adr/0017-uninstall-data-ownership.md)
- [RELEASE.md](../RELEASE.md)
- [roadmap/README.md](../roadmap/README.md)
