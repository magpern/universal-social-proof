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
