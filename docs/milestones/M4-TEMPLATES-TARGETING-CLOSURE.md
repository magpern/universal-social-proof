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
| Original tag (at closure) | `v0.4.0` | Annotated on merge commit (`af5cd9eda842229e40a581320085e1ecd97d1cc2`); see release-maintenance note below for later retarget |

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
3. Annotated tag `v0.4.0` **originally** created on that merge commit and pushed (tag object `af5cd9eda842229e40a581320085e1ecd97d1cc2`, peeled `7c34445…`)
4. At M4 closure time: `git rev-parse v0.4.0^{}` = merge SHA; header / `USP_VERSION` / CHANGELOG on that commit all said `0.4.0`
5. M4 recorded **CLOSED**

Next milestone: **M5** (`0.5.0`) — not started in this release step.

## Release-maintenance note (2026-09-07)

Published tags are **immutable** for this maintenance pass — do not force-move or recreate them. Recorded history:

| Event | Detail |
|-------|--------|
| Original M4 merge | `7c34445e1f9d3507b589fe66952ac947f9892163` |
| Original `v0.4.0` (at M4 closure) | Annotated tag object `af5cd9e…` → peeled `7c34445…` |
| Later packaging standardization | Release packaging/docs/scripts landed on `main` (through merge `c05fe078335fc991e8ef74f330d9c4ff3dd87735`); **no M4 runtime feature-contract change** |
| Current authoritative origin `v0.4.0` | Annotated tag object `6eb8e9baf46724f617e3d24d69208aeffe21cbd3` → peeled `c05fe078335fc991e8ef74f330d9c4ff3dd87735` |
| Current published release | `v0.4.1` (lightweight tag; historical exception) → `e709fb2d5bae283b07f2c50064386bcd04717a20` |

M4 remains **CLOSED** at feature version `0.4.0`. `v0.4.1` is maintenance (self-update), not a relabel of M4. M5 / M6 still not started.
