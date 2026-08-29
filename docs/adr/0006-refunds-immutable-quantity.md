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

### M1 storage type (engineering)

WooCommerce 11 stores line quantity via `wc_stock_amount()` (`int|float`, filterable). USP stores original quantity as **`DECIMAL(18,6)`** and compares refunded vs ordered using scaled-integer micros (`Quantity` helper). Do not silently cast to PHP `int`.

Full line refund: `abs( get_qty_refunded_for_item( $item_id ) ) >= ordered quantity`.

## Consequences

Stored quantity remains purposeful for optional templates without mutable “remaining qty” semantics in v1. Fractional WooCommerce quantities remain representable.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §6 · ADR-0005 · ADR-0011
