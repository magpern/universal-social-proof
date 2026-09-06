# M5 — Geography and Universal Geo Context Plan (FROZEN)

> **Status: FROZEN / APPROVED FOR IMPLEMENTATION PLANNING**  
> **Planning review verdict:** READY TO FREEZE (post narrow correction 2026-09-07)  
> **Target version:** `0.5.0`  
> **Baseline `main`:** `b0a2ad99654a2568adf87687a1fe8b3f8167a21b`  
> **Latest published release:** `v0.4.1`  
> **M4:** CLOSED (`v0.4.0`; maintenance `v0.4.1`)  
> **Schema:** `usp_db_version = 20260829m1` (**unchanged** — evidence below)  
> **UGC inspected:** `/opt/biopentra/dev/universal-geo-context` **v1.9.0**

This document is the authoritative M5 milestone specification underneath
`docs/architecture/FROZEN.md` and ADR-0002 (including M5 appendix). Do not reopen
frozen decisions during implementation unless repository evidence exposes a
genuine contradiction.

---

## 1. Objective

Introduce **visitor-country weighting** for social-proof selection via a soft
Universal Geo Context (UGC) dependency, without:

- performing IP geolocation inside USP;
- confusing visitor country with purchase country;
- expanding city/region;
- changing the public DTO;
- migrating schema;
- building M6 admin UI.

Flow:

```text
anonymous GET /notifications
→ GeoContextAdapter (UGC PHP once, or Null)
→ normalized visitor country or null
→ SelectionEngine tiered pools (preference, not hard filter)
→ product resolve under M2 budgets + M4 targeting/templates
→ public DTO unchanged
```

---

## 2. Non-goals

- City / region / postal / lat-long / browser geolocation
- USP-owned IP or header parsing
- Client `?country=` authority
- `visitor_country` (or equivalent) on the public DTO
- Persisted geo options / M6 Geography admin screen
- Schema / index migration
- Fabricated purchase geography
- Hard UGC activation dependency
- M6 diagnostics UI / M7 release work

---

## 3. Two-country model

| Concept | Source | Persistence | Used for |
|---------|--------|-------------|----------|
| **Purchase country** | Woo billing else shipping → `usp_events.country_code` (M1) | Stored | `{{country}}` / `{{location}}`; country pool membership |
| **Visitor country** | UGC during USP notifications REST | **Never** stored | Selection weighting only |

They must never overwrite each other. Visitor country must never be inferred onto
historical events. Purchase country must never be inferred from the visitor.

---

## 4. Privacy boundary

Country only. Forbidden in USP M5: city, region, postal, street, names, emails,
customer/order IDs in public DTO, IP, raw proxy headers, coordinates, browser geo.

USP consumes UGC’s normalized country result only. UGC may use infrastructure
signals internally; that remains UGC’s responsibility.

---

## 5. UGC integration evidence

| Item | Value |
|------|-------|
| Plugin | Universal Geo Context **1.9.0** |
| Detection | `function_exists( 'universal_geo_get_country_code' )` |
| Call | `universal_geo_get_country_code(): ?string` |
| Optional gate | `universal_geo_api_version() === 1` when function exists |
| Region | **Ignore** `universal_geo_get_region_code()` |
| Failures | Public helpers do not throw; miss → null |
| IP | Not USP’s concern |

### GeoContextAdapter (`src/Geo/`)

```php
interface GeoContextAdapter {
  public function country_code(): ?string;
  public function is_available(): bool;
}
```

- `UgcGeoContextAdapter` — detect + call; defensive normalize; catch `Throwable` → unavailable/null.
- `NullGeoContextAdapter` — always unavailable / null.
- Normalize: trim → uppercase → `/^[A-Z]{2}$/` else `null` (no ISO membership table in USP).
- Memoize at most **one** resolution per notifications request.

### Absence / failure

| Condition | Selection behavior |
|-----------|-------------------|
| UGC absent | M2-equivalent global/PDP path |
| null / unknown | M2-equivalent |
| malformed after normalize | M2-equivalent |
| Throwable | M2-equivalent; no public error leak |

---

## 6. REST geo gate

1. Resolve visitor country **only** inside
   `GET /wp-json/universal-social-proof/v1/notifications`.
2. Do **not** add or trust a client country query parameter.
3. Geo failure never causes 5xx by itself.
4. Preserve `Cache-Control: no-store` and params: `limit`, `product_id`,
   `page_context`, `exclude`.

---

## 7. Selection algorithm (frozen)

**Strategy:** strict tiered preference with fallback (not probabilistic quotas).
Same-country events **may fill all K**. No forced global diversity slot.
No `SQL RAND()`. PHP `shuffle` per pool. Freshness from `occurred_at` cutoff only.

### Constants

| Constant | Value |
|----------|-------|
| Preferred×country SQL | 20 |
| Preferred any-country SQL | 20 |
| Country global SQL | 80 |
| Global SQL | 80 |
| `ProductResolutionBudget::MAX` | 20 |
| `PDP_SEARCH_CAP` | **5 shared** across Tier1+Tier2 |
| K default / max | 5 / 10 |
| exclude max | 20 |

### Shared PDP_SEARCH_CAP

`PDP_SEARCH_CAP = 5` is **total** for Tier1 + Tier2 combined.

One `begin_additional_cap(PDP_SEARCH_CAP)` wraps both preferred-search phases.
Tier2 receives only unused remainder.

| Tier1 attempts | Tier2 may attempt |
|----------------|-------------------|
| 0 | 5 |
| 2 | 3 |
| 5 | 0 |

If Tier1 accepts the preferred event → skip Tier2.
Cap remains inside global `MAX=20`. Geographic pools never reset the cap.

### Non-PDP + valid visitor country V

1. Country pool LIMIT 80 → shuffle → fill K  
2. If short: global LIMIT 80 → shuffle → fill  

### PDP + valid visitor country V

1. `begin_additional_cap(5)`  
2. **Tier1:** product_id + country V LIMIT 20 → shuffle → variation-order → attempt until 1 accepted or shared cap exhausted  
3. **Tier2:** only if none accepted — product any-country LIMIT 20 → remaining shared cap  
4. `end_additional_cap()`  
5. **Tier3:** country-global LIMIT 80 → fill  
6. **Tier4:** global LIMIT 80 → fill  
7. If still short: leftover already-loaded preferred candidates (no new PDP cap)

### No visitor country

Exact M2 path (preferred + global). Zero country SQL.

### Event dedup vs product-resolution accounting

| Concern | Rule |
|---------|------|
| Event-attempt dedup | Each `public_id` processed **at most once** per REST request across all pools |
| Product memo / budget | Request-local memo + `ProductResolutionBudget` **never reset** by geo tiers |
| Memoized product | Later-tier event with same product does **not** add a USP-initiated `wc_get_product()` |
| Never reset by geo | `attempted`, product memo, `ProductResolutionBudget`, `PDP_SEARCH_CAP` |

Excluded `public_id`s and targeting/OOS/unresolved skips do not consume accepted K.
Template failure after select: omit without refill (M4).
Null purchase country: not in country SQL pools; still eligible globally; templates keep M4 behavior.

---

## 8. Index / SQL evidence (DEV 2026-09-07)

Table: `wp_usp_events` (37 rows at evidence time). Indexes match Schema:

- `status_occurred (status, occurred_at)`
- `status_country_occurred (status, country_code, occurred_at)`
- `status_product_occurred (status, product_id, occurred_at)`

**Tier-1 EXPLAIN** (`status=active` AND `product_id=P` AND `country_code=V` AND `occurred_at >= cutoff` ORDER BY `occurred_at` DESC LIMIT 20):

- `type=range`
- `possible_keys`: all three status_* indexes
- Chosen: `status_country_occurred` (`used_key_parts`: status, country_code, occurred_at); `product_id` in filter
- Product-only preferred: `status_product_occurred`
- Country-global LIMIT 80: `status_country_occurred`

**Schema decision:** **NO migration.** `DB_VERSION` remains `20260829m1`.
Hard LIMIT 20/80 + existing range indexes keep execution acceptably bounded.
Optimizer may choose country- or product-leading index; both are acceptable.
Do not add a four-column composite index in M5.

---

## 9. Worst-case request budget

| Metric | Cap |
|--------|-----|
| Candidate SQL (PDP+geo) | ≤ 4 |
| Candidate SQL (non-PDP+geo) | ≤ 2 |
| Candidate SQL (no geo) | ≤ 2 (M2) |
| Rows fetched | ≤ 200 (20+20+80+80) |
| Shared PDP preferred-search attempts | ≤ 5 |
| USP-initiated `wc_get_product` | ≤ 20 |
| UGC resolutions | ≤ 1 |
| REST K | ≤ 10 |

---

## 10. Public DTO

Unchanged:

```text
public_id
product_url
thumbnail_url
occurred_at
message
show_relative_time
```

No `visitor_country`.

---

## 11. Template semantics

| Token | Meaning |
|-------|---------|
| `{{country}}` | **Purchase** country label |
| `{{location}}` | Purchase country alias (M4) |

Visitor country never substitutes. Example: visitor SE + event DE → `{{country}}` = Germany.

---

## 12. Configuration seam (no persistence)

- Default: weighting enabled when adapter available and country non-null.
- Filter: `usp_geo_weighting_enabled` (bool).
- Thin `GeographyPolicy` (name flexible) for M6 admin binding later.
- **No** `update_option` / persisted geo settings in M5.

---

## 13. Purchase-country privacy + erasure hardening (M5 slice)

- Visitor geo never persisted → nothing to erase for visitor country.
- Privacy policy content: USP may use UGC country ephemerally for ranking; does not store visitor IP/country.
- Regression-test M1 dual-path eraser/exporter; no new PII columns.

---

## 14. Package / file plan (implementation later)

| Area | Path |
|------|------|
| Adapter | `src/Geo/` |
| Query | `CandidateQuery` / `CandidateReader` country variants |
| Engine | `SelectionEngine` tiered fill + shared PDP cap |
| REST | `NotificationsController` + internal visitor field on request |
| Policy | `GeographyPolicy` + filter |
| CI | Allow `src/Geo/`; still forbid `src/Admin/`; forbid client country param |
| Tests | unit + integration matrix below |

---

## 15. Test matrix (required)

**UGC:** unavailable; SE; lowercase; null; malformed; Throwable.

**Selection:** no visitor → M2-equivalent; abundant/sparse/no same-country; null purchase country; PDP Tier1 accept; PDP Tier1 miss → Tier2; PDP none → Tier3/4; exclude; targeting; OOS; variation; budget exhaustion; freshness.

**Shared PDP cap:** Tier1 consumes all 5; Tier1 consumes 3 → Tier2 gets 2; Tier1 accepts early → Tier2 skipped; memoized product across tiers; global `wc_get_product` ≤ 20.

**Dedup:** same `public_id` in country + global pools attempted once.

**Privacy/REST:** no visitor field in DTO; `{{country}}` = purchase; no 5xx on UGC fail; no-store; no client country authority.

**Regression:** M1 capture/suppress/refund/privacy/retention; M2 selection/REST/budget; M3 toaster; M4 templates/targeting.

---

## 16. DEV acceptance

| ID | Scenario |
|----|----------|
| A | Visitor SE; mix SE + non-SE purchases → country preference visible |
| B | Visitor country with no matches → global fallback |
| C | UGC unavailable/null → M2-equivalent |
| D | PDP + cross-country product events → Tier1–4 precedence |
| E | `{{country}}` renders purchase country, not visitor |
| F | REST JSON has no visitor-country / private geo |
| G | Behind real DEV proxy: UGC resolves requesting visitor (ADR-0002 gate) |

---

## 17. Security acceptance

Anonymous REST; no trusted client country; normalized UGC output; no anonymous writes; no visitor persistence; no PII expansion; no IP/header exposure; prepared SQL for country predicates; existing param validation preserved.

---

## 18. Implementation sequence (later)

1. Geo adapters + wiring  
2. Candidate country queries  
3. SelectionEngine tiers + shared PDP cap + shared attempted/memo  
4. REST geo gate  
5. GeographyPolicy filter  
6. Privacy policy text  
7. Tests + CI guards  
8. DEV acceptance A–G  

**Rollback:** disable via Null adapter / filter; no schema to reverse.

---

## 19. M6 / M7 exclusions

**M6:** admin Geography on/off UI, diagnostics UI, persisted options.  
**M7:** production-hardening release `v1.0.0`.

---

## 20. Planning-review checklist (resolved)

| Risk | Resolution |
|------|------------|
| PDP cap ×2 | Shared cap = 5 |
| Budget ×N | Single budget/memo never reset |
| Event re-attempt | `attempted` once |
| Tier-1 SQL | EXPLAIN range on existing indexes; LIMIT; no migration |
| Schema creep | Frozen `20260829m1` |
| Starvation | Tier3/4 + leftover preferred |

**Unresolved Product Owner decisions:** none.
