# Milestone roadmap — Universal Social Proof

**Status:** Frozen with [architecture/FROZEN.md](../architecture/FROZEN.md).  
**Scope:** M0–M7 only. **No M8.**

Versions are **cumulative**: each milestone builds on the prior closed version. M7 intentionally jumps from `0.6.0` to **`1.0.0`** as the first production-recommended complete v1 release.

| Milestone | Version after closure | Objective | Release tag |
|-----------|----------------------|-----------|-------------|
| **M0** | `0.0.0` | Repository + architecture foundation (scaffold, CI, ADR shells, HPOS declare, capability/menu ADR) | — (scaffold) |
| **M1** | `0.1.0` | Genuine WooCommerce capture + country-only storage; characterize+freeze `occurred_at` resolver; terminal suppress; refunds; erasure hooks; retention | `v0.1.0` (**CLOSED**) |
| **M2** | `0.2.0` | Selection engine + cache-safe REST; resolution budget; UUIDv4 `public_id`; response K ≤ 10. Plan: [M2-SELECTION-REST-PLAN.md](../milestones/M2-SELECTION-REST-PLAN.md) (**frozen**, implementation not closed) | `v0.2.0` |
| **M3** | `0.3.0` | Front-end notification component (vanilla JS) | `v0.3.0` |
| **M4** | `0.4.0` | Server-side templates (incl. `{{quantity}}`, omitted from default) + product/page targeting | `v0.4.0` |
| **M5** | `0.5.0` | UGC visitor-country weighting + purchase-country privacy + erasure hardening + REST geo acceptance gate | `v0.5.0` |
| **M6** | `0.6.0` | Admin UX under WooCommerce + diagnostics | `v0.6.0` |
| **M7** | **`1.0.0`** | Hardening, acceptance, **first production-recommended release** | **`v1.0.0`** |

Annotated freeze tags may use `mN-…-freeze`. Plugin header / `USP_VERSION` / changelog must agree on any release-tagged commit.

## Out of this roadmap

- Region / city geography  
- Fabricated purchase events  
- Generic multi-event platform  
- Any M8+

Authoritative detail: [architecture/FROZEN.md](../architecture/FROZEN.md) §15–§20.
