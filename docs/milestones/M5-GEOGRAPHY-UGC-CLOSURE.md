# M5 — Geography and Universal Geo Context Closure

**Status:** CLOSED  
**Verdict:** PASS — M5 CLOSED / `v0.5.0` RELEASED  
**Version:** `0.5.0`  
**Release tag:** `v0.5.0` (annotated) → peeled `495b12da46b2af3f292114b50c0ca58358e27abd`  
**Tag object:** `64162689aa447955c48675b361cddf869c2b5646`  
**GitHub Release:** https://github.com/magpern/universal-social-proof/releases/tag/v0.5.0  

**Freeze merge SHA:** `1d3c959372b97168b38d077e319a427308110d96` (PR #7)  
**Implementation PR:** https://github.com/magpern/universal-social-proof/pull/8  
**Implementation merge SHA:** `76572f3bbe02724d911a78a9751c2ac6798a62a1`  
**Post-merge CI (implementation):** SUCCESS — https://github.com/magpern/universal-social-proof/actions/runs/34095273906  
**Release-state PR:** https://github.com/magpern/universal-social-proof/pull/9  
**Release-state merge SHA:** `495b12da46b2af3f292114b50c0ca58358e27abd`  
**Post-merge CI (release-state):** SUCCESS — https://github.com/magpern/universal-social-proof/actions/runs/34096294890  
**Real proxy UGC gate:** **PASS** — see [M5-GEOGRAPHY-UGC-ACCEPTANCE.md](M5-GEOGRAPHY-UGC-ACCEPTANCE.md)  
**Release workflow:** SUCCESS — https://github.com/magpern/universal-social-proof/actions/runs/34098173577  
**Private update publish:** SUCCESS — https://github.com/magpern/universal-social-proof/actions/runs/34098173644  

**Schema:** `20260829m1` unchanged  
**Public DTO:** unchanged  
**Production:** untouched  
**M6 / M7:** not started  

## Delivered

| Area | Result |
|------|--------|
| Soft UGC | `src/Geo/` — `GeoContextAdapter`, `UgcGeoContextAdapter`, `NullGeoContextAdapter`, `GeographyPolicy` + `usp_geo_weighting_enabled` |
| Selection | Tiered preference; shared `PDP_SEARCH_CAP=5`; `ProductResolutionBudget::MAX=20`; event-attempt dedup; product memo |
| REST | Visitor country resolved only on notifications GET; no client `?country=`; `Cache-Control: no-store` |
| Templates | `{{country}}` / `{{location}}` remain purchase-country tokens |
| Privacy | Ephemeral visitor country; privacy-policy suggestion; no visitor persistence |

## Explicit absences

No `src/Admin/`; no persisted geo options; no schema/index migration; no USP IP/header geolocation; no region/city; no visitor fields on public DTO.

## Release closure

1. PR #8 merged (`76572f3…`); post-merge CI green  
2. Real DEV reverse-proxy UGC acceptance **PASS**  
3. PR #9 release-state merged (`495b12d…`); `Stable tag: 0.5.0`; post-merge CI green  
4. Annotated tag `v0.5.0` on `495b12d…` (immutable; do not retarget)  
5. GitHub Release published with zip + sha256  
6. Private update-server publish verified live  
7. M5 **CLOSED**; next milestone is **M6 planning/freeze only**

## Release assets

- `universal-social-proof-0.5.0.zip`  
- `universal-social-proof-0.5.0.zip.sha256`  
