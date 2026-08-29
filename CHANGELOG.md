# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to the milestone versioning in [docs/adr/0013-version-release-policy.md](docs/adr/0013-version-release-policy.md).

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
