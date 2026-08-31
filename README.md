# Universal Social Proof

Portable WooCommerce plugin for **genuine**, privacy-conscious purchase social-proof notifications.

**Architecture status:** [FROZEN](docs/architecture/FROZEN.md) — Product Owner approved.  
**Implementation status:** M3 storefront toaster infrastructure (`0.3.0`, tag `v0.3.0`) — presentation runtime is display-gated on `message`; live M2 responses remain visually inert until M4.

| Item | Value |
|------|-------|
| Plugin name | Universal Social Proof |
| Slug / text domain | `universal-social-proof` |
| Namespace | `UniversalSocialProof\` |
| Composer | `magpern/universal-social-proof` |
| Current version | `0.3.0` (M3 **CLOSED**; tag `v0.3.0`) |
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
