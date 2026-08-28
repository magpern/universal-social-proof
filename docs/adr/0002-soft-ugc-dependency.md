# ADR-0002 — Soft UGC dependency / Null adapter

## Status

Accepted (architecture freeze)

## Context

Visitor-country weighting improves relevance but must not hard-require Universal Geo Context.

## Decision

- Soft dependency via `GeoContextAdapter` with a Null adapter when UGC is absent or incompatible.
- Prefer calling UGC’s public PHP API inside USP’s anonymous REST request.
- v1 uses visitor **country** only; ignore UGC region for matching.
- Never trust client-supplied country.
- M5 includes an acceptance gate that UGC resolves the requesting visitor correctly behind the real proxy during USP REST.

## Consequences

Plugin operates fully without UGC (global selection). Geo weighting is an enhancement, not a hard requirement.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §9 · ADR-0008
