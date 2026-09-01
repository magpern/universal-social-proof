# Universal Social Proof

Portable WooCommerce plugin for **genuine**, privacy-conscious purchase social-proof notifications.

**Architecture status:** [FROZEN](docs/architecture/FROZEN.md) — Product Owner approved.  
**Implementation status:** M4 templates + targeting (`0.4.0`, tag `v0.4.0`) — server-rendered `message` activates the M3 toaster; `show_relative_time` coordinates `{{time_ago}}` with relative-time chrome.


| Item | Value |
|------|-------|
| Plugin name | Universal Social Proof |
| Slug / text domain | `universal-social-proof` |
| Namespace | `UniversalSocialProof\` |
| Composer | `magpern/universal-social-proof` |
| Current version | `0.4.0` (M4 **CLOSED**; tag `v0.4.0`) |
| First production-recommended release | **v1.0.0** (M7) |

## Requirements

- PHP `>= 8.1` (production target PHP 8.3)
- WordPress `>= 6.5`
- WooCommerce `>= 8.2` (tested against 11.0.x)

## Documentation

| Document | Purpose |
|----------|---------|
| [docs/architecture/FROZEN.md](docs/architecture/FROZEN.md) | **Authoritative** M0–M7 implementation specification |
| [docs/roadmap/README.md](docs/roadmap/README.md) | Milestone versions and cumulative roadmap |
| [docs/GOVERNANCE.md](docs/GOVERNANCE.md) | How to change frozen architecture |
| [docs/adr/README.md](docs/adr/README.md) | Architectural Decision Records |
| [docs/milestones/M0-FOUNDATION-PLAN.md](docs/milestones/M0-FOUNDATION-PLAN.md) | M0 implementation plan |
| [docs/milestones/M0-FOUNDATION-CLOSURE.md](docs/milestones/M0-FOUNDATION-CLOSURE.md) | M0 closure record |
| [docs/milestones/M1-CAPTURE-STORAGE-PLAN.md](docs/milestones/M1-CAPTURE-STORAGE-PLAN.md) | M1 capture/storage plan |
| [docs/milestones/M1-CAPTURE-STORAGE-CLOSURE.md](docs/milestones/M1-CAPTURE-STORAGE-CLOSURE.md) | M1 closure record |
| [docs/milestones/M2-SELECTION-REST-PLAN.md](docs/milestones/M2-SELECTION-REST-PLAN.md) | M2 selection + REST plan (frozen) |
| [docs/milestones/M3-STOREFRONT-TOASTER-PLAN.md](docs/milestones/M3-STOREFRONT-TOASTER-PLAN.md) | M3 toaster plan (frozen) |
| [docs/milestones/M3-STOREFRONT-TOASTER-CLOSURE.md](docs/milestones/M3-STOREFRONT-TOASTER-CLOSURE.md) | M3 closure record (`v0.3.0` **CLOSED**) |
| [docs/milestones/M4-TEMPLATES-TARGETING-PLAN.md](docs/milestones/M4-TEMPLATES-TARGETING-PLAN.md) | M4 templates/targeting plan (frozen) |
| [docs/milestones/M4-TEMPLATES-TARGETING-CLOSURE.md](docs/milestones/M4-TEMPLATES-TARGETING-CLOSURE.md) | M4 closure record (`v0.4.0` **CLOSED**) |

## Local development

```bash
composer install
composer lint
composer phpcs
composer test:unit
# Integration (requires MariaDB/MySQL; see tests/bin/install-wp.sh):
bash tests/bin/install-wp.sh
composer test:integration
composer ci
bash scripts/build-release-package.sh   # build the deployable plugin ZIP + checksum
```

## Release process

Pushing an annotated `vX.Y.Z` tag on `main` runs
`.github/workflows/release.yml`: it re-runs the mandatory quality gates, builds
`universal-social-proof-<version>.zip` + `.zip.sha256` via
`scripts/build-release-package.sh`, verifies every version declaration matches
the tag (per [ADR-0013](docs/adr/0013-version-release-policy.md)), and publishes
a GitHub Release with both assets. Nothing generated is committed. Full details:
[docs/RELEASE.md](docs/RELEASE.md).

## Product principle

**Real social proof, not fabricated FOMO.** Administrators cannot create purchase-looking notifications that did not originate from eligible WooCommerce transactions.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
