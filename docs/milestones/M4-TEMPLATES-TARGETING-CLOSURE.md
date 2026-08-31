# M4 — Templates and Targeting Closure

**Status:** CLOSED  
**Verdict:** PASS  
**Version:** `0.4.0`  
**Release tag:** `v0.4.0` → `7c34445e1f9d3507b589fe66952ac947f9892163`  
**PR:** https://github.com/magpern/universal-social-proof/pull/5 (merged)  
**Merge commit (`main`):** `7c34445e1f9d3507b589fe66952ac947f9892163`  
**Baseline `main` (pre-M4):** `f6075b2ed632cce28effe0ec679cfea64e4bed96`  
**Plan freeze:** `be7612a53afe6cc2b675c6044e5b907896862e16`  
**Feature branch (pre-merge tip):** `5df15f2d6228d4ce04285e66a9a5ddb1d3e3b2c3`  
**Production:** untouched  
**M5 / M6:** not started  

## Commits

| Role | SHA | Subject |
|------|-----|---------|
| Plan freeze + ADR/FROZEN amendment | `be7612a53afe6cc2b675c6044e5b907896862e16` | docs(m4): freeze templates and targeting architecture |
| Template package | `e3f26ea` | feat(template): add constrained server message rendering |
| Targeting | `f89fbbe` | feat(targeting): add bounded product and page targeting |
| REST DTO | `c3934d0` | feat(rest): expose M4 message presentation metadata |
| Frontend chrome gate | `d0ae609` | feat(frontend): coordinate relative-time chrome with server message |
| Tests | `3aad427` / `31f8a51` | test(m4): cover templates targeting and activation (+ integration guards) |
| CI | `8c53810` | ci(m4): enforce M4 scope boundaries |
| Docs / version | `e3803de` / `e58016a` | docs(m4): record implementation and version state |
| Brace remediation | `5df15f2d6228d4ce04285e66a9a5ddb1d3e3b2c3` | fix(template): reject stray braces in grammar validation |
| Merge to main | `7c34445e1f9d3507b589fe66952ac947f9892163` | Merge pull request #5 from magpern/feature/m4-templates-targeting |
| Tag | `v0.4.0` | Annotated tag on merge commit (`af5cd9eda842229e40a581320085e1ecd97d1cc2`) |

## Delivered

| Area | Result |
|------|--------|
| Templates | `src/Template` — whitelist grammar, plain-text `message`, `used_time_ago` → `show_relative_time` |
| Tokens | `{{product}}`, `{{country}}`, `{{location}}` (purchase-country alias), `{{time_ago}}`, `{{quantity}}` |
| Default | `Someone purchased {{product}}` (translated; filter `usp_notification_template`; no option) |
| Targeting | `TargetingPolicy` page gates; selection-level `ProductTargetingPolicy` (`usp_excluded_product_ids`; no option) |
| REST DTO | `public_id`, `product_url`, `thumbnail_url`, `occurred_at`, `message`, `show_relative_time` |
| M3 chrome | Suppress `<time>` when `show_relative_time === false` |
| Schema | `20260829m1` unchanged |

## Architecture amendments

- ADR-0011 (2026-08-31): six-field M4 DTO; `show_relative_time` semantics; `{{location}}` alias  
- FROZEN §11 / M4 milestone notes aligned  

## Tests and CI

| Gate | Result |
|------|--------|
| PR #5 head `5df15f2` | SUCCESS — [33380915961](https://github.com/magpern/universal-social-proof/actions/runs/33380915961) |
| Post-merge `main` `7c34445` | SUCCESS — [33392538466](https://github.com/magpern/universal-social-proof/actions/runs/33392538466) |

Jobs: Lint/PHPCS/M4 policy + JS, unit PHP 8.1/8.3/8.4, integration PHP 8.3 / WC 11.0.1.

## Explicit absences

No Geo/UGC visitor weighting; no Admin UI; no persisted template/exclusion options; no fake purchases; no product-name snapshots; no client template engine; no schema migration.

## Release closure (post-merge)

1. PR #5 merged to `main` as `7c34445e1f9d3507b589fe66952ac947f9892163`
2. Post-merge CI on that commit: SUCCESS
3. Annotated tag `v0.4.0` created on that merge commit and pushed
4. Tag verified: `git rev-parse v0.4.0^{}` = merge SHA; tag object `af5cd9eda842229e40a581320085e1ecd97d1cc2`
5. Header / `USP_VERSION` / CHANGELOG on the tagged commit all say `0.4.0`
6. M4 recorded **CLOSED**

Next milestone: **M5** (`0.5.0`) — not started in this release step.
