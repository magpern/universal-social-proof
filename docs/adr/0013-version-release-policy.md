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
- Milestone release tags: `v0.N.0` for M1–M6; **`v1.0.0`** for M7.
- Maintenance/patch releases between milestones (for example `v0.4.1`) are allowed when needed; they do **not** relabel the closed milestone version.
- Plugin header, `USP_VERSION`, and changelog must agree on the tagged commit.
- **Official release tags must be annotated Git tags** (`git tag -a vX.Y.Z -m "…"`). Lightweight tags are not accepted for new releases.
- **Historical exception:** `v0.4.1` was published as a lightweight tag and remains immutable; do not rewrite or convert it in place.

## Consequences

`0.x` communicates development milestones; `1.0.0` communicates completed v1 contract after hardening. Annotated tags carry a stable message and peel to the release commit (`tag^{}`) even when the tag object SHA differs from the commit SHA.

## Automation

The header/`USP_VERSION`/`CHANGELOG.md` agreement is enforced in CI by
`scripts/ci/check.sh` (`composer ci`). Tag-triggered publishing
(`.github/workflows/release.yml`) additionally verifies all three equal the
pushed `vX.Y.Z` tag before creating the GitHub Release, never rewrites
version files, and refuses non-annotated release tags for future publishes.
See [../RELEASE.md](../RELEASE.md).

## Related

[roadmap/README.md](../roadmap/README.md) · [architecture/FROZEN.md](../architecture/FROZEN.md) §15 · [../RELEASE.md](../RELEASE.md)
