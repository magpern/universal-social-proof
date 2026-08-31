# ADR-0011 — Server-side template model

## Status

Accepted (architecture freeze)

## Context

Client and server must not diverge on template token semantics. Templates must not allow executable PHP or shortcodes.

## Decision

- Constrained token grammar rendered **server-side** into a privacy-safe `message` string in the REST DTO.
- Front end presents `message` and may refresh relative-time from `occurred_at` only.
- M4 tokens include at least `{{product}}`, `{{location}}` / `{{country}}`, `{{time_ago}}`, and **`{{quantity}}`**.
- `{{quantity}}` = original purchased quantity at `occurred_at`.
- Default template **omits** quantity.
- Invalid templates rejected at admin validation; escape on render.

## Consequences

Single source of template truth; quantity field remains purposeful without default exposure.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §6, §11 · ADR-0006

---

## Amendment — 2026-08-30 (Product Owner clarification)

**Status:** Accepted (explicit PO approval, 2026-08-30)

FROZEN §11 and this ADR originally described the eventual public DTO as including a server-rendered `message`. M4 owns template rendering.

**Clarification (do not rewrite the historical decision above as if it had always said this):**

- **M2 establishes the base public DTO** with exactly: `public_id`, `product_url`, `thumbnail_url`, `occurred_at`.
- **M2 omits `message`.** M2 must not implement templates, a token parser, a temporary English message, a placeholder `message`, or `message: null`.
- **M4 additively introduces `message`** via server-side templates into the same REST allowlist.

This is an architecture clarification under [GOVERNANCE.md](../GOVERNANCE.md) §4, not a silent implementation shortcut.

---

## Amendment — 2026-08-31 (M4 presentation metadata and location alias)

**Status:** Accepted (explicit PO approval, 2026-08-31)

### M4 public DTO

M4 additively extends the public notification allowlist to exactly:

```text
public_id
product_url
thumbnail_url
occurred_at
message
show_relative_time
```

- **`message`:** required non-empty plain-text string on successfully projected M4 DTOs; server-rendered from the constrained token grammar; no HTML semantics.
- **`show_relative_time`:** required boolean presentation metadata on M4-produced DTOs. Contains no provenance, geography, or PII.

Semantics:

| `show_relative_time` | Meaning |
|----------------------|---------|
| `true` | Template did **not** consume `{{time_ago}}`. M3 shows its existing relative-time chrome derived from `occurred_at` (live client refresh). |
| `false` | Template **did** consume `{{time_ago}}`. M3 suppresses separate relative-time chrome for that notification to avoid duplicated time. |

Internal renderer result carries `message` + `used_time_ago`. Projection maps:

```text
show_relative_time = ! used_time_ago
```

Do **not** guess time presence by inspecting the finished message string.

Absent `show_relative_time` on older/M3-shaped clients/fixtures defaults to `true` (show chrome). Normal M4 REST responses emit the boolean explicitly.

Known duplicated time presentation (message containing `{{time_ago}}` **and** visible M3 chrome) is **rejected**.

Front end must not re-implement template token semantics. M3 may continue to refresh relative-time chrome from `occurred_at` only when `show_relative_time` is true.

### `{{location}}` grammar clarification (country-only v1)

Country-only v1 geography is already frozen. Separately, M4 clarifies:

> `{{location}}` is an **alias** of the localized purchase-country display used by `{{country}}`.

No city or region. Purchase country only (stored `country_code` → WooCommerce country label). Not visitor geography.

### Default template (M4)

```text
Someone purchased {{product}}
```

Omits quantity, country/location, and `{{time_ago}}` → therefore `show_relative_time = true`.

This is an architecture clarification under [GOVERNANCE.md](../GOVERNANCE.md) §4.
