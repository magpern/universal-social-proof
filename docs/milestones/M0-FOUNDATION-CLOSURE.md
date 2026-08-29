# M0 — Foundation Closure

**Status:** PASS (local validation complete; GitHub Actions blocked by account billing/spending limit — not by M0 code)  
**Version:** `0.0.0`  
**Branch:** `feature/m0-foundation`  
**Baseline `main`:** `8535e2532b478acce60c3020226367a03df29e8b`
**PR:** https://github.com/magpern/universal-social-proof/pull/1  
**Final branch HEAD:** `ad8ac8c95899476d4048d2c8aa4fdefc9fc51560`

## Commits

| Role | SHA | Subject |
|------|-----|---------|
| Plan | `19c86a150e30285a20980487e0d0586c631427c7` | docs: freeze M0 foundation plan |
| Bootstrap | `1286dc88ab1689cdef26c429988c509cec21f50b` | feat: bootstrap Universal Social Proof foundation |
| Tests | `2a6ad562d2f4192b2d029f55369b03f5967715b3` | test: add M0 validation foundation |
| CI | `91babefcc42ec9a1688926272bc75cdbbeaa69af` | ci: add repository quality gates |
| Closure | `e3b3299166d9b45caa3066e39940f88f2390e4ff` | docs: close M0 foundation |

## Delivered

- Plugin identity: Universal Social Proof / `universal-social-proof` / `UniversalSocialProof\` / `USP_` / `usp_`
- Main file `universal-social-proof.php` at version `0.0.0`
- Composer package `magpern/universal-social-proof` with PSR-4 autoload
- `Plugin` composition root + `WooCommerceGate`
- WooCommerce-absent admin notice; WC-present idempotent `Plugin::init()`
- HPOS `custom_order_tables` declaration via `FeaturesUtil`
- No activation/deactivation hooks (no M0 persistent state)
- PHPCS (WPCS + WooCommerce sniffs), PHPUnit unit + integration, `scripts/ci/check.sh`
- GitHub Actions: quality, unit matrix (8.1/8.3/8.4), integration (PHP 8.3 / WC 11.0.1)
- README, CHANGELOG, CONTRIBUTING, LICENSE (GPL-2.0-or-later)

## Compatibility (actual)

| Axis | Value |
|------|-------|
| Min PHP | 8.1 |
| Production target | PHP 8.3 (freeze environment) |
| CI unit PHP | 8.1, 8.3, 8.4 |
| CI integration | PHP 8.3 + WooCommerce 11.0.1 |
| WordPress floor | 6.5 |
| WooCommerce floor | 8.2 (`Requires Plugins: woocommerce`) |
| WC tested up to | 11.0 |

## Bootstrap architecture

1. ABSPATH guard → version/path constants → PHP 8.1 guard  
2. Composer autoload (admin notice if missing)  
3. `before_woocommerce_init` → HPOS declare  
4. `plugins_loaded` → WC gate → notice or `Plugin::init()`  
5. M0 `init()` sets initialized flag only — no feature registrations  

## Validation results (local)

Host has no system PHP; validation ran in Docker (`ugeo-php8.3-mysqli`, `composer:2`, ephemeral MariaDB 11.4).

| Gate | Result |
|------|--------|
| `composer validate --strict` | PASS |
| `scripts/ci/check.sh` | PASS |
| `composer phpcs` / `php vendor/bin/phpcs` | PASS |
| `composer test:unit` | PASS (9 tests) |
| `composer test:integration` | PASS (6 tests) after `tests/bin/install-wp.sh` |

## CI status

Workflow `.github/workflows/ci.yml` is present and triggered on PR #1.  
GitHub did **not** start runners: *“The job was not started because recent account payments have failed or your spending limit needs to be increased.”*  

Locally equivalent gates are green (see Validation results). Re-run Actions after billing is restored.

## DEV verification

**Not performed on DEV WordPress.** `apps/wordpress/compose.yml` does not bind-mount `/opt/biopentra/dev/universal-social-proof`. Adding that mount would be a VPS compose change outside this repository milestone; left for operators. Integration suite covers plugin load + HPOS under WP/WC 11.0.1.

## Deviations from M0 plan

1. `scripts/ci/check.sh` falls back to a PHP structural check of `composer.json` when the Composer CLI is absent (plain PHP Docker images). CI with `shivammathur/setup-php` still runs full `composer validate`.
2. PHPCS `PrefixAllGlobals` lists only `usp`/`USP` (three-letter frozen prefix), matching Universal Multicurrency — WPCS ignores short prefixes for matching when a longer namespace prefix is also listed.

## Known limitations

- No DEV bind-mount / activation yet  
- No admin menu (intentional; M6)  
- No `v0.0.0` tag (intentional; scaffold only)  

## Explicit non-delivery (M1+ not started)

Confirmed absent: `usp_events`, purchase capture hooks, REST notifications, frontend toaster/assets, UGC adapter, privacy exporter/eraser implementation, settings UI, fake events, region/city, `occurred_at` resolver freeze.

Architecture freeze (`docs/architecture/FROZEN.md`) and ADRs were not rewritten.

## Next milestone

**M1** (`0.1.0`): genuine capture + country-only storage; characterize and freeze `occurred_at` resolver (ADR-0004); terminal suppress; refunds; erasure hooks; retention — only after this PR is reviewed/merged.
