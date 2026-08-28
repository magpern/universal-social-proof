# ADR-0009 — UUIDv4 public identifiers

## Status

Accepted (architecture freeze)

## Context

Public event ids must not be reversible obfuscations of order or internal ids (e.g. Hashids).

## Decision

- Each event receives a **UUIDv4** (`public_id`) at insert.
- REST and session exclusion use `public_id` only.
- Internal `id`, `source_order_id`, and `source_item_id` never appear in the public DTO.

## Consequences

Uniform identifier format for tests and clients; no enumerable order linkage via public ids.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §5, §13 · ADR-0003
