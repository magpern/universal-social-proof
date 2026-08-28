# ADR-0005 — Terminal suppression and idempotency

## Status

Accepted (architecture freeze)

## Context

Cancelled or fully refunded purchases must not remain as active social proof. Reactivating a suppressed row after admin status recovery would mislead (e.g. “19 days ago”) and interact badly with `occurred_at`-based retention.

## Decision

- One event per `(source_order_id, source_item_id)` enforced by unique key.
- State machine: `active → suppressed → purge/erasure` only.
- **Suppression is terminal.** No `suppressed → active`.
- Admin moving an order back to qualifying status must not resurrect marketing content and must not insert a second row (unique key blocks re-insert).
- Platform order delete → suppress (not default hard-delete); privacy erasure → hard-delete.

## Consequences

Simpler lifecycle; no reactivation occurrence semantics. Unique key retains suppressed rows until retention or erasure.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §6 · ADR-0006
