# Release process — Universal Social Proof

Implements the version/tag policy in
[`adr/0013-version-release-policy.md`](adr/0013-version-release-policy.md).

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

Tag convention: `v0.N.0` for milestones M1–M6, `v1.0.0` for M7 (ADR-0013).
The workflow trigger is the generic `v[0-9]+.[0-9]+.[0-9]+` (plus a `-<pre>`
suffix → GitHub prerelease); ADR-0013 governs *which* such tags are cut.

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
bash scripts/build-release-package.sh 0.4.0     # must match the plugin file

cd dist
sha256sum -c universal-social-proof-<version>.zip.sha256
unzip -l universal-social-proof-<version>.zip
```

## Cutting a release

1. Bump `Version:`, `USP_VERSION`, and add the `## [<version>]` `CHANGELOG.md`
   section in one commit (also update `docs/roadmap` if the milestone closes).
2. Merge to **`main`** (the only release branch) and wait for CI to go green.
3. Push an annotated tag per ADR-0013:
   ```bash
   git tag -a v0.4.0 -m "Universal Social Proof 0.4.0"
   git push origin v0.4.0
   ```
4. `release.yml` re-runs `composer ci`, `composer phpcs`, `composer test:js`,
   `composer test:unit`; builds the ZIP; verifies packaged version == tag ==
   header == constant and the changelog section exists; generates the SHA-256
   checksum; and creates the GitHub Release with the ZIP + `.zip.sha256`.
5. Both assets appear on the Release page.

## Using the artifact for deployment

Normal WordPress plugin archive. Verify before deploying:

```bash
sha256sum -c universal-social-proof-<version>.zip.sha256
```

Generated ZIPs/checksums are CI outputs — `.gitignore`d, never committed.

## Recovering from a failed release

- Failure before "Create GitHub Release" → nothing published. Fix version
  declarations on `main`, delete and re-create the tag.
- Failure during publish → delete the partial GitHub Release, re-run the
  workflow.
- Always tag a commit already on `main`.
