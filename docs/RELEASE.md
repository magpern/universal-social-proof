# Release process — Universal Social Proof

Implements the version/tag policy in
[`adr/0013-version-release-policy.md`](adr/0013-version-release-policy.md).

## Current release state (M5 / v0.5.0)

| Item | Value |
|---|---|
| Latest published release | **`v0.5.0`** |
| Release commit (peeled tag) | `495b12da46b2af3f292114b50c0ca58358e27abd` |
| Annotated tag object | `64162689aa447955c48675b361cddf869c2b5646` |
| GitHub Release | https://github.com/magpern/universal-social-proof/releases/tag/v0.5.0 |
| Release workflow | https://github.com/magpern/universal-social-proof/actions/runs/34098173577 |
| Private update publish | https://github.com/magpern/universal-social-proof/actions/runs/34098173644 |
| Implementation merge | `76572f3bbe02724d911a78a9751c2ac6798a62a1` (PR #8) |
| Freeze merge | `1d3c959372b97168b38d077e319a427308110d96` (PR #7) |
| Prior published release | `v0.4.1` |
| M4 feature release | `v0.4.0` (**CLOSED**) |
| Schema | `20260829m1` unchanged |
| Real proxy UGC gate | **PASS** ([acceptance](milestones/M5-GEOGRAPHY-UGC-ACCEPTANCE.md)) |
| M5 closure | [M5-GEOGRAPHY-UGC-CLOSURE.md](milestones/M5-GEOGRAPHY-UGC-CLOSURE.md) (**CLOSED**) |
| M6 / M7 | Not started |
| Production WordPress deploy | Not implied by GitHub/update-server publication |

## Prior note (post-v0.4.1 maintenance)

| Item | Value |
|---|---|
| Latest published release (at that time) | **`v0.4.1`** |
| Release commit | `e709fb2d5bae283b07f2c50064386bcd04717a20` |
| M4 feature release | `v0.4.0` (**CLOSED**; do not relabel as 0.4.1) |
| Current `v0.4.0` peeled commit | `c05fe078335fc991e8ef74f330d9c4ff3dd87735` (packaging normalization; see M4 closure note) |
| Schema | `20260829m1` unchanged |
| Production WordPress deploy | Not implied by GitHub/update-server publication |

The v0.4.1 maintenance reconciled documentation and future tag policy only. Tags `v0.4.0` and `v0.4.1` were **not** moved, rewritten, or republished.

## Canonical version source

Per ADR-0013 the plugin header, the `USP_VERSION` constant, and `CHANGELOG.md`
must agree on the tagged commit. Concretely:

| Location | Field |
|---|---|
| `universal-social-proof.php` | `Version:` plugin header |
| `universal-social-proof.php` | `USP_VERSION` constant |
| `CHANGELOG.md` | a `## [<version>]` section |

`scripts/ci/check.sh` (run by `composer ci`, a mandatory CI gate) already
enforces header/constant/changelog agreement for the current milestone version.
`scripts/build-release-package.sh` re-checks header == constant and that
`CHANGELOG.md` has the matching section. `.github/workflows/release.yml`
additionally refuses to publish unless all of them equal the pushed Git tag
(leading `v` removed). CI never rewrites version files.

Tag convention: `v0.N.0` for milestones M1–M6, `v1.0.0` for M7 (ADR-0013);
maintenance patches between milestones use `v0.N.P` when needed. The workflow
trigger is the generic `v[0-9]+.[0-9]+.[0-9]+` (plus a `-<pre>` suffix → GitHub
prerelease); ADR-0013 governs *which* such tags are cut.

**Annotated tags required:** every new official release tag must be created with
`git tag -a`. The release workflow rejects lightweight tags. Historical
exception: published lightweight `v0.4.1` remains immutable.

Annotated-tag semantics: the tag **object** SHA may differ from the peeled
commit SHA (`vX.Y.Z^{}`). Packaging and verification always use the commit/tree
checked out from the tag ref (peeled), never the tag-object SHA as if it were
a commit.

## Package identity

| Item | Value |
|---|---|
| Deployable directory | `universal-social-proof/` (sole top-level entry) |
| ZIP | `dist/universal-social-proof-<version>.zip` |
| Checksum | `dist/universal-social-proof-<version>.zip.sha256` |

**Included:** `universal-social-proof.php`, `src/`, `assets/`, `composer.json`,
`README.md`, `LICENSE`, `CHANGELOG.md`, and a freshly generated production
`vendor/` (autoloader only — no third-party runtime dependencies).

**Excluded:** `.git/`, `.github/`, `scripts/`, `tests/`, `docs/`,
`package.json`, `CONTRIBUTING.md`, `composer.lock`, `phpcs.xml.dist`,
`phpunit*.xml.dist`, `.phpunit.result.cache`, `.gitignore`, and any previous
build output. The packaging script fails if any appear in the ZIP.

## Build and validate locally

```bash
composer install
bash scripts/build-release-package.sh          # version from the plugin file
bash scripts/build-release-package.sh 0.4.1     # must match the plugin file

cd dist
sha256sum -c universal-social-proof-<version>.zip.sha256
unzip -l universal-social-proof-<version>.zip
```

## Cutting a release

1. Bump `Version:`, `USP_VERSION`, and add the `## [<version>]` `CHANGELOG.md`
   section in one commit (also update `docs/roadmap` if the milestone closes).
2. Merge to **`main`** (the only release branch) and wait for CI to go green.
3. Push an **annotated** tag per ADR-0013 (lightweight tags are rejected):
   ```bash
   git tag -a v0.5.0 -m "Universal Social Proof 0.5.0"
   git push origin v0.5.0
   ```
4. `release.yml` confirms the tag is annotated; re-runs `composer ci`,
   `composer phpcs`, `composer test:js`, `composer test:unit`; builds the ZIP;
   verifies packaged version == tag == header == constant and the changelog
   section exists; generates the SHA-256 checksum; and creates the GitHub
   Release with the ZIP + `.zip.sha256`.
5. Both assets appear on the Release page.

## Using the artifact for deployment

Normal WordPress plugin archive. Verify before deploying:

```bash
sha256sum -c universal-social-proof-<version>.zip.sha256
```

Generated ZIPs/checksums are CI outputs — `.gitignore`d, never committed.
GitHub Release or private update-server publication does **not** by itself
prove production WordPress is running that version.

## Recovering from a failed release

- Failure before "Create GitHub Release" → nothing published. Fix version
  declarations on `main`, delete and re-create the **annotated** tag.
- Failure during publish → delete the partial GitHub Release, re-run the
  workflow.
- Always tag a commit already on `main`.
- Do not force-move tags that already have a published GitHub Release.
