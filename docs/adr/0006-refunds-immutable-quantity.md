# ADR-0006 — Refund semantics and immutable quantity

## Status

Accepted (architecture freeze)

## Context

Partial refunds must not rewrite purchase history. Quantity may appear in templates but is omitted from the default string.

## Decision

- `quantity` stores **original purchased quantity** at capture; **immutable** after insert.
- Partial refund (remaining qty &gt; 0): keep event **active**; do not rewrite `quantity`.
- Full line refund (refunded qty ≥ original qty): **terminal suppress**.
- M4 token `{{quantity}}` means original quantity at `occurred_at`; default template **omits** it.
- M1 characterizes HPOS-safe WC refund APIs to detect full line refund reliably (API detail is engineering evidence, not a product-contract change).

## Consequences

Stored quantity remains purposeful for optional templates without mutable “remaining qty” semantics in v1.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §6 · ADR-0005 · ADR-0011
