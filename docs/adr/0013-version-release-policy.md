# ADR-0013 — Version and release policy

## Status

Accepted (architecture freeze) + **amendment 2026-09-07 (M6/M7 combined program)**

## Context

Milestones need clear version communication. Declaring `0.7.0` as the “finished” production release is less clear than `1.0.0`.

The combined M6/M7 program designates the next **published** release after
`v0.5.0` as **`v1.0.0`**. Requiring a public `v0.6.0` solely because M6 uses
runtime `0.6.0` would add release ceremony without product value.

## Decision

| Milestone | Runtime version |
|-----------|-----------------|
| M0 | `0.0.0` |
| M1–M6 | `0.1.0` … `0.6.0` |
| M7 | **`1.0.0`** — first production-recommended complete v1 |

- Versions are cumulative; no M8 in this freeze.
- **Published** milestone release tags historically: `v0.N.0` for M1–M5; **`v1.0.0`** for M7.
- **Amendment (2026-09-07):** a public annotated tag / GitHub Release / private
  update publish for **`v0.6.0` is not required**. M6 may ship runtime
  `0.6.0` on `main` while Stable tag remains at the last published release
  (`0.5.0`) until the v1 release-state PR advances Stable to `1.0.0`.
  Packaging already allows Stable lag until release.
- Maintenance/patch releases between milestones (for example `v0.4.1`) are allowed when needed; they do **not** relabel the closed milestone version.
- On any **published** tagged commit: plugin header, `USP_VERSION`, Stable tag, and changelog must agree with that tag.
- During pre-publish M6/M7 work: header/`USP_VERSION`/CHANGELOG agree with the **runtime** milestone version; Stable may lag.
- **Official release tags must be annotated Git tags** (`git tag -a vX.Y.Z -m "…"`). Lightweight tags are not accepted for new releases.
- **Historical exception:** `v0.4.1` was published as a lightweight tag and remains immutable; do not rewrite or convert it in place.

## Consequences

`0.x` communicates development milestones; `1.0.0` communicates completed v1 contract after hardening. Annotated tags carry a stable message and peel to the release commit (`tag^{}`) even when the tag object SHA differs from the commit SHA. Operators updating from published releases see `v0.5.0` → `v1.0.0` without an intermediate public `v0.6.0`.

## Automation

The header/`USP_VERSION`/`CHANGELOG.md` agreement is enforced in CI by
`scripts/ci/check.sh` (`composer ci`). Tag-triggered publishing
(`.github/workflows/release.yml`) additionally verifies all three equal the
pushed `vX.Y.Z` tag before creating the GitHub Release, never rewrites
version files, and refuses non-annotated release tags for future publishes.
See [../RELEASE.md](../RELEASE.md).

## Related

[roadmap/README.md](../roadmap/README.md) · [architecture/FROZEN.md](../architecture/FROZEN.md) §15 · [../RELEASE.md](../RELEASE.md) · [M6-M7-V1-PROGRAM.md](../milestones/M6-M7-V1-PROGRAM.md)
