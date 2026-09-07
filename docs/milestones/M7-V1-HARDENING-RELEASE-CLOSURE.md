# M7 — v1 Hardening / Release Closure

> **Status: M7 CLOSED**  
> **v1 program: COMPLETE**  
> **Closure date:** 2026-09-07  
> **Production:** untouched

## Baseline

| Item | Value |
|------|-------|
| M7 implementation baseline | `08b529a8139bc3682f8790590f9ae8858d2047b4` |
| M6 INTERNAL CLOSED | yes (runtime `0.6.0`; no public `v0.6.0`) |
| DB_VERSION | `20260829m1` (unchanged) |
| Event schema changed | **NO** |

## Implementation

| Item | Value |
|------|-------|
| Branch | `feature/m7-v1-hardening` |
| Implementation PR | https://github.com/magpern/universal-social-proof/pull/12 |
| Final PR HEAD | `0fa20e2…` |
| PR CI | PASS (`34107982222`) |
| Implementation merge SHA | `9481833c1042a9476bba3ad9a019d91ad963710a` |
| Post-merge CI | PASS (`34108112006`) |

### Hardening summary

- Template token plain-text sanitization
- Diagnostics `manage_woocommerce` gate
- Logger context allowlist
- Dismiss control accessible name
- Runtime `1.0.0` with Stable lag until release-state
- Lifecycle / budget regression tests

### Review verdicts

| Area | Verdict |
|------|---------|
| Security | PASS |
| Privacy | PASS |
| Performance budgets | PASS (K≤10, resolve≤20, PDP cap 5, UGC≤1/request, no `ORDER BY RAND`) |
| Diagnostics product/order calls | 0 |
| Cache/proxy | PASS (`no-store`, CF DYNAMIC / BYPASS) |
| Accessibility | PASS (narrow fixes) |
| Woo/HPOS | PASS (declared + exercised in suite) |
| UGC failure | soft / global selection continues |
| Upgrade / clean-install / lifecycle | PASS (automated + DEV) |
| Browser / admin | PASS (DEV smoke + M6 regression tests) |
| Packaging (candidate) | PASS with Stable lag |

## V1 acceptance

| Item | Value |
|------|-------|
| `V1_ACCEPTANCE_GATE` | **PASS** ([gate](M7-V1-ACCEPTANCE-GATE.md)) |
| Candidate evidence | [M7-V1-CANDIDATE-ACCEPTANCE.md](M7-V1-CANDIDATE-ACCEPTANCE.md) |

## Release-state

| Item | Value |
|------|-------|
| Branch | `release/v1.0.0` |
| PR | https://github.com/magpern/universal-social-proof/pull/13 |
| CI | PASS (`34108351821`) |
| `V1_RELEASE_COMMIT` | `082f71760b66cf0d42cdfd092441694792fba4c4` |
| Stable at release | `1.0.0` |

## Annotated tags / GitHub Releases

### v1.0.0 (immutable)

| Item | Value |
|------|-------|
| Tag type | annotated |
| Tag object | `d3ef27d70d2aedd8e9a10decbcf22aea9e32fa9e` |
| Peeled commit | `082f71760b66cf0d42cdfd092441694792fba4c4` |
| GitHub Release | https://github.com/magpern/universal-social-proof/releases/tag/v1.0.0 |
| Release workflow | https://github.com/magpern/universal-social-proof/actions/runs/34108501576 |
| Assets | `universal-social-proof-1.0.0.zip`, `.sha256` |
| Private update publish | SUCCESS — https://github.com/magpern/universal-social-proof/actions/runs/34108501532 |

**Known packaging defect (not rewritten):** `v1.0.0` ZIP omitted `uninstall.php`.

### v1.0.1 patch (ADR-0017 packaging)

| Item | Value |
|------|-------|
| PR | https://github.com/magpern/universal-social-proof/pull/14 |
| Merge / tag peel | `dcdfc3d6856a503459a34b8b3eab24067f03c1e4` |
| Tag type | annotated |
| GitHub Release | https://github.com/magpern/universal-social-proof/releases/tag/v1.0.1 |
| Release workflow | https://github.com/magpern/universal-social-proof/actions/runs/34109141833 |
| Assets | `universal-social-proof-1.0.1.zip` (**includes `uninstall.php`**), `.sha256` |
| Private update publish | SUCCESS — https://github.com/magpern/universal-social-proof/actions/runs/34109141660 |
| Update metadata | offers `1.0.1` |

### v1.0.2 patch (Plugins action links)

| Item | Value |
|------|-------|
| PR | https://github.com/magpern/universal-social-proof/pull/15 |
| Merge / tag peel | `47b00fde4b7442324ece83c69b01bfad1c387481` |
| Tag type | annotated |
| GitHub Release | https://github.com/magpern/universal-social-proof/releases/tag/v1.0.2 |
| Release workflow | https://github.com/magpern/universal-social-proof/actions/runs/34109794470 |
| Private update publish | SUCCESS — https://github.com/magpern/universal-social-proof/actions/runs/34109794507 |
| Change | Plugins-row **Settings \| Diagnostics**; `#usp-diagnostics` anchor; compact Enabled/Disabled status |

## Post-publish update path

- Private metadata advanced through `1.0.0` → `1.0.1` → **`1.0.2`**
- Recommended operator package: **`v1.0.2`**
- DEV bind-mount remains active at current `main` runtime; production untouched
- `v0.5.0` → `v1.0.2` is the recommended operator update path

## Explicit non-releases

- **`v0.6.0` never publicly released**
- Production WordPress **not** deployed by this program

## Final runtime state (after closure docs commit may advance HEAD)

| Item | On `v1.0.2` tag |
|------|-----------------|
| Runtime | `1.0.2` |
| Stable tag | `1.0.2` |
| Latest published | `v1.0.2` |
| M6 | INTERNAL CLOSED |
| M7 | CLOSED |
| v1 program | COMPLETE |
