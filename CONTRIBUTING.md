# Contributing

Contributions follow the internal review process. The authoritative product contract is [docs/architecture/FROZEN.md](docs/architecture/FROZEN.md).

## Public-repository rules

- Never commit credentials, tokens, private keys, customer/order data, production configuration, or secrets.
- Treat issues and pull requests as potentially public.

## Branch workflow

1. Branch from `main` using conventional prefixes: `feat/`, `fix/`, `chore/`, `docs/`, `test/`, `ci/`.
2. Open a pull request; CI must pass before merge.
3. Milestones are implemented in order (M0 → M7) unless Product Owner approves otherwise.

## Commit messages

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): subject

body

Closes #LP-0
```

## Code standards

- PHP 8.1+ with `declare(strict_types=1);` in `src/`.
- Namespace: `UniversalSocialProof\`.
- Prefixes: `USP_` constants, `usp_` hooks.
- Admin capability (when admin exists): `manage_woocommerce`; menu under WooCommerce (ADR-0012). Do not add an empty settings screen in early milestones without an operational reason.
- Do not invent M1+ features (capture, `usp_events`, REST notifications, frontend toaster, UGC weighting, fake events, region/city) ahead of their milestones.

## Local validation

```bash
composer install
composer ci
composer phpcs
composer test:unit
```

Integration tests need a database and `bash tests/bin/install-wp.sh` (see CI workflow for coordinates: PHP 8.3, WooCommerce 11.0.1).
