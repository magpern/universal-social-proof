# M7 — v1 acceptance gate (post-merge)

> **V1_ACCEPTANCE_GATE:** **PASS**  
> **Date:** 2026-09-07  
> **Main SHA (implementation merge):** `9481833c1042a9476bba3ad9a019d91ad963710a`  
> **Runtime:** `1.0.0`  
> **DB_VERSION:** `20260829m1`  
> **Production:** untouched

## Evidence

| Gate | Result |
|------|--------|
| Post-merge CI (`main` run `34108112006`) | PASS |
| Plugin version on DEV | `1.0.0` |
| Settings version | `1` |
| Notifications REST | `200` + `Cache-Control: no-store` |
| Proxy cache | CF `DYNAMIC` / `x-bp-cache: BYPASS` |
| Public DTO | allowlist only; no visitor/provenance |
| Storefront toaster shell | present |
| Event schema | unchanged |

Candidate hardening evidence: [M7-V1-CANDIDATE-ACCEPTANCE.md](M7-V1-CANDIDATE-ACCEPTANCE.md).

Release-state advances Stable tag to `1.0.0` without runtime behavior changes.
