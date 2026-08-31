# M4 — Templates and Targeting Plan (FROZEN)

> **Status: FROZEN / APPROVED**  
> **Product Owner approval:** 2026-08-31  
> **Target version:** `0.4.0`  
> **Baseline `main`:** `f6075b2ed632cce28effe0ec679cfea64e4bed96`  
> **M3:** CLOSED (`v0.3.0` → `b0328d7505d6ec341de4ec1fd4b8c27e78ac2281`)  
> **Schema:** `usp_db_version = 20260829m1` (unchanged)

This document is the authoritative M4 milestone specification underneath `docs/architecture/FROZEN.md` and ADR-0011 (including 2026-08-31 amendment). All PO decisions are **resolved**. Do not reopen them during implementation unless repository evidence exposes a genuine contradiction.

---

## 1. Objective

Activate the existing M3 toaster by supplying server-rendered plain-text `message` values, plus product/page targeting, without redesigning M2 selection budgets or the M3 state machine.

Flow:

```text
eligible stored event
→ bounded M2 selection (+ ProductTargetingPolicy)
→ current PublicProduct (already resolved)
→ M4 TemplateRenderer
→ public DTO with message + show_relative_time
→ M3 canPresent()
→ visible toaster
```

---

## 2. Public REST DTO (M4)

Exactly:

```text
public_id
product_url
thumbnail_url
occurred_at
message
show_relative_time
```

| Field | Rules |
|-------|--------|
| `message` | Required non-empty plain-text string on successful M4 DTOs |
| `show_relative_time` | Required boolean; `true` → M3 shows relative-time chrome; `false` → suppress chrome because `{{time_ago}}` was consumed |

Internal renderer: `{ message, used_time_ago }` with `show_relative_time = !used_time_ago`. Do not infer from message text.

Absent `show_relative_time` on older clients/fixtures → treat as `true`.

Never expose: quantity, country_code, product/variation ids, provenance, buyer PII.

`Cache-Control: no-store` unchanged. Anonymous GET only.

---

## 3. Token grammar

Allowed tokens only:

```text
{{product}}
{{country}}
{{location}}
{{time_ago}}
{{quantity}}
```

Syntax: `{{token_name}}` with `[a-z][a-z0-9_]*`. No args, filters, nesting, PHP, shortcodes, HTML execution.

Max template length: **500** characters.

Unknown/malformed → fail closed (omit notification).

### Token semantics

| Token | Meaning |
|-------|---------|
| `{{product}}` | Current M2-resolved `PublicProduct::$name` (zero extra `wc_get_product`) |
| `{{country}}` | Localized purchase-country label from stored ISO via WC countries API |
| `{{location}}` | **M4 clarification:** alias of `{{country}}` display (country-only v1; no city/region) |
| `{{time_ago}}` | Server snapshot relative time from `occurred_at` (aligned with M3 buckets) |
| `{{quantity}}` | Immutable original purchased quantity; display-normalized |

Empty optional country/location → `''`. Empty product / invalid quantity-when-used / uncomputable time-when-used → fail.

### Quantity display

```text
1.000000 → 1
2.000000 → 2
1.500000 → 1.5
0.250000 → 0.25
10.000000 → 10
```

No integer cast. No automatic singular/plural.

---

## 4. Default template and configuration

**Default (translated whole string):**

```text
Someone purchased {{product}}
```

Omits quantity, country/location, `{{time_ago}}` → `show_relative_time = true`.

**M4 source:** translated code default + validated filter `usp_notification_template`.  
**No `wp_option` in M4.** Invalid filter output → fall back to validated default.

Persistence/UI → M6.

---

## 5. Selection vs render (critical distinction)

### Product targeting (during selection — may continue walk)

```text
candidate
→ PublicProductResolver (merchandising eligibility unchanged)
→ ProductTargetingPolicy
→ if excluded: continue EXISTING bounded candidate-pool walk
→ else accept SelectedEvent
```

Excluded products **do not consume final K accepted slots**.

Do **not** put operator exclusion in `PublicProductResolver::is_publicly_eligible()`.

### Template failure (after selection — no refill)

```text
SelectedEvent[] ≤ K from SelectionEngine
→ TemplateRenderer per event
→ failure → omit
→ NO REFILL
→ response may be < K
```

Worst-case renders ≤ K ≤ 10. Budgets unchanged (80 / 20 / 20 / 5 / K).

---

## 6. Targeting

### ProductTargetingPolicy

- Default: empty exclusion set  
- Filter: `usp_excluded_product_ids` (validate positive ints, dedupe, cap 200)  
- No option  
- Apply after resolve; check presented id + parent id  
- Excludes **events**, not entire toaster / PDP load  

### TargetingPolicy (enqueue)

**Allow:** PDP, shop, category/archive, search, homepage, ordinary content frontend.  
**Exclude:** checkout, cart, account, admin, REST context, WP-CLI, feeds.

Cart/account = presentation defaults (not immutable architecture). Checkout = architecture-aligned.

---

## 7. Narrow M3 change

Only:

- validate optional boolean `show_relative_time` (absent → true; wrong type → invalid DTO);
- suppress/clear `<time>` when `false`;
- restore chrome correctly when a later toast has `true`.

Do **not** redesign state machine, queue, storage, no-repeat, dismiss, rotation, timing, batching.

---

## 8. Packages

```text
src/Template/TemplateRenderer.php
src/Template/TemplateContext.php
src/Template/TemplateSettings.php
src/Targeting/TargetingPolicy.php
src/Targeting/ProductTargetingPolicy.php
```

Optional small `RenderResult` VO if useful.

---

## 9. Explicit exclusions

No M5 UGC/visitor geo/IP/client geo/region/city.  
No M6 admin UI or persisted template/exclusion options.  
No fake purchases, buyer names, anonymous writes, schema migration, product-name snapshots, client template engine, production deploy.

---

## 10. Acceptance

DEV real-event: default template → visible toaster + chrome; filter template with `{{time_ago}}` → no duplicated time. Schema remains `20260829m1`. Version on branch `0.4.0`. Tag `v0.4.0` only after merge (not this feature branch).

---

## 11. Resolved PO decisions

| Decision | Resolution |
|----------|------------|
| Default template | `Someone purchased {{product}}` |
| Quantity | Immutable original; display normalize as above; no plural; omit from default |
| Country | Purchase country token; omit from default |
| Location | Alias of country display (M4 clarification) |
| Unknown token | Fail closed |
| Missing country | Empty substitution |
| Template config | Code default + filter; no option |
| Page matrix | As §6 |
| Product exclusion | Events only; filter; no option |
| Render failure | Omit; no refill |
| `{{time_ago}}` | Supported; `show_relative_time` coordinates chrome (ADR-0011 2026-08-31) |
| Targeting vs resolver | Selection-level policy; resolver merchandising-only |

**These decisions are closed.**
