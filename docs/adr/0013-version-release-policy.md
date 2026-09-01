# ADR-0013 — Version and release policy

## Status

Accepted (architecture freeze)

## Context

Milestones need clear version communication. Declaring `0.7.0` as the “finished” production release is less clear than `1.0.0`.

## Decision

| Milestone | Version |
|-----------|---------|
| M0 | `0.0.0` |
| M1–M6 | `0.1.0` … `0.6.0` |
| M7 | **`1.0.0`** — first production-recommended complete v1 |

- Versions are cumulative; no M8 in this freeze.
- Release tags: `v0.N.0` for M1–M6; **`v1.0.0`** for M7.
- Plugin header, `USP_VERSION`, and changelog must agree on the tagged commit.

## Consequences

`0.x` communicates development milestones; `1.0.0` communicates completed v1 contract after hardening.

## Automation

The header/`USP_VERSION`/`CHANGELOG.md` agreement is enforced in CI by
`scripts/ci/check.sh` (`composer ci`). Tag-triggered publishing
(`.github/workflows/release.yml`) additionally verifies all three equal the
pushed `vX.Y.Z` tag before creating the GitHub Release, and never rewrites
version files. See [../RELEASE.md](../RELEASE.md).

## Related

[roadmap/README.md](../roadmap/README.md) · [architecture/FROZEN.md](../architecture/FROZEN.md) §15 · [../RELEASE.md](../RELEASE.md)
