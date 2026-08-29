# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to the milestone versioning in [docs/adr/0013-version-release-policy.md](docs/adr/0013-version-release-policy.md).

## [0.1.0] - M1 genuine capture and storage

### Added

- `{prefix}usp_events` schema (`DECIMAL(18,6)` quantity) with Migrator (`usp_db_version`).
- Genuine capture on first transition into `processing`/`completed` via `woocommerce_order_status_changed`.
- `OccurredAtResolver`: `date_paid` → `date_completed` → `date_created` → null (fail closed).
- Terminal suppression (cancel/fail/full refund/line remove/order delete) with post-insert race convergence.
- Privacy exporter/eraser dual-path + Woo pre-anonymization hook; no PII in USP tables.
- Action Scheduler retention purge by `occurred_at` (default 60 days, clamp 7–90).

### Notes

- No storefront notifications, REST selection, templates, or UGC (M2+).
- Tag `v0.1.0` only after merge to `main` (not from the feature branch).

## [0.0.0] - M0 foundation

### Added

- Plugin bootstrap (`universal-social-proof.php`) with identity `0.0.0`, Composer PSR-4 autoload, and PHP version guard.
- WooCommerce availability gate and graceful admin notice when WooCommerce is inactive.
- HPOS (`custom_order_tables`) compatibility declaration via `FeaturesUtil`.
- Minimal `UniversalSocialProof\Plugin` composition root (no feature hooks).
- PHPUnit unit and integration foundations, PHPCS/WPCS, and GitHub Actions CI.
- M0 plan and exclusion policy guards.

### Notes

- No purchase capture, event storage, REST notifications, frontend toaster, UGC integration, or admin settings UI.
- No `v0.0.0` release tag — M0 is scaffold only. First production-recommended release is M7 `v1.0.0`.
