# M5 — Geography / UGC acceptance evidence

**Branch / release:** published `v0.5.0` (peeled `495b12da46b2af3f292114b50c0ca58358e27abd`)  
**Freeze baseline:** `1d3c959372b97168b38d077e319a427308110d96` (PR #7)  
**Implementation PR:** #8 → merge `76572f3bbe02724d911a78a9751c2ac6798a62a1`  
**Runtime version:** `0.5.0`  
**WordPress Stable tag:** `0.5.0`  
**DB_VERSION:** `20260829m1` (unchanged)  
**Closure:** [M5-GEOGRAPHY-UGC-CLOSURE.md](M5-GEOGRAPHY-UGC-CLOSURE.md)

## Automated

| Suite | Result |
|-------|--------|
| Unit | PASS — 73 tests (1 skipped) |
| Integration | PASS — 74 tests / 2092 assertions |
| JS | PASS — 22 tests |
| PHPCS | PASS |
| CI scope | PASS |
| PR #8 CI | PASS — https://github.com/magpern/universal-social-proof/actions/runs/34093073653 |
| Post-merge CI (`76572f3`) | PASS — https://github.com/magpern/universal-social-proof/actions/runs/34095273906 |

## DEV fixture / adapter scenarios (pre-merge)

| ID | Result |
|----|--------|
| A–G automated matrix | PASS (see implementation PR) |

## Real reverse-proxy UGC acceptance (HARD RELEASE GATE)

**PROXY_UGC_GATE=PASS** (2026-09-07, DEV only)

### Environment

| Item | Value |
|------|-------|
| Site | `https://dev.biopentra.eu` |
| USP | active `0.5.0` (bind-mount `main`) |
| UGC | active `1.9.0`, API version `1` |
| Paths | `GET /wp-json/universal-geo-context/v1/context`, `GET /wp-json/universal-social-proof/v1/notifications` |

### Proxy path evidence

Responses included: `server: cloudflare`, `cf-ray`, `cf-cache-status: DYNAMIC`, `x-bp-cache: BYPASS`, `Cache-Control: no-store`. Requests were **not** made to WordPress loopback / localhost behind the proxy.

### UGC baseline

Observed normalized visitor country through real proxy:

- `country_code`: **`FR`** (`/^[A-Z]{2}$/`)
- `region_code`: `null` (ignored by USP M5)

No visitor IP was recorded in USP evidence.

### USP consumption

Temporary DEV-only mu-plugin probe logged only the normalized ISO code from `usp_geo_weighting_enabled` during notifications selection:

- `country=FR enabled=1`

Probe files were removed after the gate (`usp-m5-proxy-acceptance-probe.php`, template probe).

### Scenarios

| ID | Result |
|----|--------|
| A Same-country preference | PASS — `limit=3` returned three purchase-country **FR** events while DE fixtures existed |
| B Sparse fallback | PASS — one FR kept + two non-FR after excluding other FR ids |
| C PDP Tier1 | PASS — `product_id=6935&page_context=product` selected FR event for that product |
| D Purchase `{{country}}` | PASS — visitor FR, selected purchase DE → message `Bought in Germany` (not France); template filter restored |
| Soft UGC unavailable | PASS — deactivated UGC; notifications still `200` + `no-store`; UGC REST `404`; UGC reactivated and restored `FR` |
| DTO / privacy | PASS — allowlist-only keys; no visitor/geo columns; probes removed |

### Release gate note

Implementation PR #8 was allowed to merge with proxy acceptance deferred. Annotated **`v0.5.0` requires this PASS** (satisfied).
