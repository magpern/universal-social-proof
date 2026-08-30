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
