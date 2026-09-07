# Milestone roadmap — Universal Social Proof

**Status:** Frozen with [architecture/FROZEN.md](../architecture/FROZEN.md).  
**Scope:** M0–M7 only. **No M8.**

Versions are **cumulative**: each milestone builds on the prior closed version. M7 intentionally jumps from `0.6.0` to **`1.0.0`** as the first production-recommended complete v1 release.

**Latest published release:** `v0.5.0` (**CLOSED** — [closure](../milestones/M5-GEOGRAPHY-UGC-CLOSURE.md)).  
**M6:** **INTERNAL CLOSED** (runtime `0.6.0`; Stable tag remains `0.5.0`; no public `v0.6.0`) — [M6-ADMIN-DIAGNOSTICS-CLOSURE.md](../milestones/M6-ADMIN-DIAGNOSTICS-CLOSURE.md).  
**Next published release:** **`v1.0.0`**.  
**Next work:** **M7** implementation/hardening — [M7-V1-HARDENING-RELEASE-PLAN.md](../milestones/M7-V1-HARDENING-RELEASE-PLAN.md).

| Milestone | Version after closure | Objective | Release tag |
|-----------|----------------------|-----------|-------------|
| **M0** | `0.0.0` | Repository + architecture foundation (scaffold, CI, ADR shells, HPOS declare, capability/menu ADR) | — (scaffold) |
| **M1** | `0.1.0` | Genuine WooCommerce capture + country-only storage; characterize+freeze `occurred_at` resolver; terminal suppress; refunds; erasure hooks; retention | `v0.1.0` (**CLOSED**) |
| **M2** | `0.2.0` | Selection engine + cache-safe REST; resolution budget; UUIDv4 `public_id`; response K ≤ 10. Plan: [M2-SELECTION-REST-PLAN.md](../milestones/M2-SELECTION-REST-PLAN.md) (**frozen**) | `v0.2.0` (**CLOSED**) |
| **M3** | `0.3.0` | Front-end notification component (vanilla JS). Plan: [M3-STOREFRONT-TOASTER-PLAN.md](../milestones/M3-STOREFRONT-TOASTER-PLAN.md) (**frozen**) | `v0.3.0` (**CLOSED**) |
| **M4** | `0.4.0` | Server-side templates (incl. `{{quantity}}`, omitted from default) + product/page targeting. Plan: [M4-TEMPLATES-TARGETING-PLAN.md](../milestones/M4-TEMPLATES-TARGETING-PLAN.md) (**frozen**) | `v0.4.0` (**CLOSED**) |
| **M5** | `0.5.0` | UGC visitor-country weighting + purchase-country privacy + erasure hardening + REST geo acceptance gate. Plan: [M5-GEOGRAPHY-UGC-PLAN.md](../milestones/M5-GEOGRAPHY-UGC-PLAN.md); closure: [M5-GEOGRAPHY-UGC-CLOSURE.md](../milestones/M5-GEOGRAPHY-UGC-CLOSURE.md) | `v0.5.0` (**CLOSED**) |
| **M6** | `0.6.0` (runtime) | Admin UX under WooCommerce + diagnostics. Plan: [M6-ADMIN-DIAGNOSTICS-PLAN.md](../milestones/M6-ADMIN-DIAGNOSTICS-PLAN.md); closure: [M6-ADMIN-DIAGNOSTICS-CLOSURE.md](../milestones/M6-ADMIN-DIAGNOSTICS-CLOSURE.md) | **no public tag** (**INTERNAL CLOSED**; Stable `0.5.0`) |
| **M7** | **`1.0.0`** | Hardening, acceptance, **first production-recommended release**. Plan: [M7-V1-HARDENING-RELEASE-PLAN.md](../milestones/M7-V1-HARDENING-RELEASE-PLAN.md) | **`v1.0.0`** |

Program: [M6-M7-V1-PROGRAM.md](../milestones/M6-M7-V1-PROGRAM.md).

Annotated freeze tags may use `mN-…-freeze`. Official release tags must be **annotated** (`git tag -a`). On published tags, plugin header / `USP_VERSION` / Stable tag / changelog must agree.

## Out of this roadmap

- Region / city geography  
- Fabricated purchase events  
- Generic multi-event platform  
- Any M8+

Authoritative detail: [architecture/FROZEN.md](../architecture/FROZEN.md) §15–§20.
