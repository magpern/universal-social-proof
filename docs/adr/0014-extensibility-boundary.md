# ADR-0014 — Extensibility boundary

## Status

Accepted (architecture freeze)

## Context

Later event types (reviews, cart activity, scarcity) may be desirable, but v1 must stay small and must not become a generic event platform.

## Decision

- Prefer small seams around capture → eligibility → privacy/projection → selection → presentation.
- Do **not** build a generic multi-event marketing platform in M0–M7.
- Future non-purchase notification types (if ever) must not masquerade as purchase activity.
- Fabricated purchase events remain prohibited.

## Consequences

Clean plugin boundary; future types require explicit product approval and must not weaken the genuine-purchase contract.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §1, §20 · ADR-0001
