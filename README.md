# Universal Social Proof

Portable WooCommerce plugin for **genuine**, privacy-conscious purchase social-proof notifications.

**Architecture status:** [FROZEN](docs/architecture/FROZEN.md) — Product Owner approved.  
**Implementation status:** M2 selection + REST (`0.2.0`) — bounded candidate selection and anonymous notifications read API; **no storefront toaster yet** (M3).

| Item | Value |
|------|-------|
| Plugin name | Universal Social Proof |
| Slug / text domain | `universal-social-proof` |
| Namespace | `UniversalSocialProof\` |
| Composer | `magpern/universal-social-proof` |
| Current version | `0.2.0` (M2; tag `v0.2.0` after merge) |
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
| [docs/milestones/M2-SELECTION-REST-CLOSURE.md](docs/milestones/M2-SELECTION-REST-CLOSURE.md) | M2 implementation complete; PR open, not yet released |

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
```

## Product principle

**Real social proof, not fabricated FOMO.** Administrators cannot create purchase-looking notifications that did not originate from eligible WooCommerce transactions.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
