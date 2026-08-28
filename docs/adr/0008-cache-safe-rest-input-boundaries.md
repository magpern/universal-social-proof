# ADR-0008 — Cache-safe REST and input boundaries

## Status

Accepted (architecture freeze)

## Context

Full-page cache must not freeze one visitor’s notification set for another. REST query params are client-controlled.

## Decision

- Cached HTML may contain only an empty shell + config; events load via `GET /wp-json/universal-social-proof/v1/notifications` with `Cache-Control: no-store`.
- Validate and cap inputs: `product_id` (optional positive int); `page_context` (allowlist enum); `exclude` (max 20 UUIDv4); `limit` (1..10, default 5).
- **`page_context` influences selection; it is not an authorization boundary.** Enqueue controls normal UX; do not pretend client assertions enforce page eligibility as security.
- Response size K ≤ 10; selection is server-side.

## Consequences

Honest trust model; bounded anonymous load; FPC-safe delivery aligned with host `/wp-json/` bypass policy.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §10 · ADR-0010
