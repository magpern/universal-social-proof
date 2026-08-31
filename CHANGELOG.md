# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to the milestone versioning in [docs/adr/0013-version-release-policy.md](docs/adr/0013-version-release-policy.md).

## [0.4.0] - M4 server-rendered messages and targeting

### Added

- `src/Template` package: constrained whitelist grammar, plain-text `message`, `show_relative_time` coordination.
- Tokens: `{{product}}`, `{{country}}`, `{{location}}` (purchase-country alias), `{{time_ago}}`, `{{quantity}}`.
- Default template: `Someone purchased {{product}}` (translated whole string; filter `usp_notification_template`; no option).
- `src/Targeting`: page `TargetingPolicy`; selection-level `ProductTargetingPolicy` (`usp_excluded_product_ids` filter; no option).
- Public DTO adds `message` + `show_relative_time` (ADR-0011 2026-08-31).
- Narrow M3 chrome gate: suppress `<time>` when `show_relative_time === false`.

### Notes

- Schema unchanged (`20260829m1`). No Geo/Admin. No M4 persisted settings.
- Tag `v0.4.0` only after merge to `main`.

## [0.3.0] - M3 storefront toaster presentation infrastructure

### Added

- `src/Frontend` package: bootstrap config, asset loader, empty shell renderer.
- Vanilla `assets/js/usp-toaster.js` + `assets/css/usp-toaster.css` (single JS source; no bundler; JS ≤16 KiB / CSS ≤6 KiB).
- Display gate `canPresent`: visible toast requires non-empty `message` (M4 will supply it).
- In-memory shown-ID source of truth with sessionStorage persistence; client full-set no-repeat filter; wire `exclude` ≤20.
- Bounded rotation (max 3 batches); inert live M2 path stops after one successful non-presentable batch.

### Notes

- Normal operation against the M2 DTO is **intentionally visually inert** (no `message` yet). Not a defect.
- Test/visual fixtures are test-only under `tests/`; no PHP/REST/DB fixture injection.
- No schema or `usp_db_version` change (`20260829m1`).
- Tag `v0.3.0` only after merge to `main`.

## [0.2.0] - M2 selection engine and public notifications API

### Added

- Bounded candidate reader (`CandidateQuery` / `CandidateReader`): indexed recent window, LIMIT 80 global / 20 PDP preferred, PHP shuffle (no `ORDER BY RAND()`).
- `PublicProductResolver` with hard `wc_get_product()` budget of 20 and request-local memoization.
- `SelectionEngine` with separate preferred/global pools, PDP preferred-search cap of 5, and frozen variation fallback semantics.
- Anonymous `GET /wp-json/universal-social-proof/v1/notifications` (`Cache-Control: no-store`).
- Public DTO allowlist: `public_id`, `product_url`, `thumbnail_url`, `occurred_at` (UTC ISO-8601 with `Z`). **No `message` in M2** (M4 adds it additively; ADR-0011 amendment).

### Notes

- No schema or `usp_db_version` change (`20260829m1`).
- No storefront toaster, templates, UGC weighting, or admin UI (M3+).
- Tag `v0.2.0` only after merge to `main` (not from the feature branch).

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
