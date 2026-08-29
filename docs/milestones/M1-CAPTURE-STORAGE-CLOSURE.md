# M1 — Capture + Storage Closure

**Status:** CLOSED  
**Verdict:** PASS  
**Version:** `0.1.0`  
**Release tag:** `v0.1.0` → `0151f1d5d679bfbc89f3a7b4b0489fbef7a0222d`  
**PR:** https://github.com/magpern/universal-social-proof/pull/2 (merged)  
**Merge commit (`main`):** `0151f1d5d679bfbc89f3a7b4b0489fbef7a0222d`  
**Baseline `main` (pre-M1):** `5c7baf96bb25778beab0bfd04aa67a48b99107ba`  
**Plan freeze:** `d47f2b55d44fe253cc623580a9bd2607ddfcc70f`  
**Feature branch (pre-merge tip):** `045aa7b3631fbe33326cf06a5bc435d12e10ee9e`  
**Production:** untouched  

## Commits

| Role | SHA | Subject |
|------|-----|---------|
| Plan freeze | `d47f2b55d44fe253cc623580a9bd2607ddfcc70f` | docs: freeze M1 capture and storage plan |
| Storage | `88a07d37e45f177e90844fa00bc4d80f08f721fd` | feat(storage): add M1 usp_events schema and repository |
| Capture | `a79031de364df9ce54071338b0d547c69a17f9d3` | feat(capture): capture genuine WooCommerce purchases |
| Privacy/retention | `4180fb1966a627a2b23cef14464e7ebd4d527481` | feat(privacy): add privacy erasure and retention purge |
| Tests | `c310220fab63124ad7b2364adc3df196e80812f0` | test: add M1 capture and lifecycle coverage |
| ADRs/changelog | `1e4272709b24fd9cc8c55dcd08a260140b122620` | docs: accept M1 ADRs and changelog for 0.1.0 |
| Closure (branch) | `758ebed806f3d52f8fc2a037535d1b04a63a2fd4` | docs: close M1 capture and storage |
| Merge to main | `0151f1d5d679bfbc89f3a7b4b0489fbef7a0222d` | Merge pull request #2 from magpern/feature/m1-capture-storage |
| Tag | `v0.1.0` | Annotated tag on merge commit |

## Verdict summary

M1 delivers a trustworthy internal purchase-event layer: schema, genuine WooCommerce capture, terminal lifecycle, privacy dual-path, and retention. Storefront notifications and M2+ surfaces remain absent.

## Schema and migration

| Item | Result |
|------|--------|
| Table | `{prefix}usp_events` |
| Schema id | `usp_db_version` = `20260829m1` |
| Quantity | `DECIMAL(18,6)` |
| Uniques | `(source_order_id, source_item_id)`, `public_id` |
| Activation | `register_activation_hook` → `Migrator::upgrade_now()` |
| Runtime | `Migrator::maybe_upgrade_controlled()` on Plugin init + capture path |
| Idempotent | Yes (lease lock + version option after successful dbDelta) |

## Capture

| Item | Result |
|------|--------|
| Seam | `woocommerce_order_status_changed` only when `$to ∈ {processing, completed}` |
| Authenticity | Genuine WC order lines only; no fake/demo/manual purchase writers |
| Identity | `wp_generate_uuid4()` with one collision retry |
| Country | Billing → shipping → null; `[A-Z]{2}` |
| `occurred_at` | `date_paid` → `date_completed` → `date_created` → **null / skip** |
| `captured_at` | Insert-time UTC; immutable after insert |
| Race | Best-effort pre-check + insert + re-fetch order + terminal re-eval |

## Suppression / refunds / deletion

| Path | Reason |
|------|--------|
| Cancelled / failed | `cancelled` / `failed` |
| Cumulative full qty refund | `refund_full` (original quantity unchanged) |
| Partial refund | Remains `active` |
| Line remove | `line_removed` via `woocommerce_before_delete_order_item` |
| Order trash/delete | `order_deleted` |
| Reactivation | Forbidden |

## Privacy

| Path | Behavior |
|------|----------|
| Exporter | Occurrence time, country, quantity, public UUID — no source IDs |
| Eraser | Paginated `wc_get_orders` by email/user → hard delete (priority 5) |
| Pre-anonymize | `woocommerce_privacy_before_remove_order_personal_data` |
| Limitation | Prospective only; already-anonymized history is fail-closed (ADR-0007) |
| PII in USP | None |

## Retention

| Item | Result |
|------|--------|
| Default / clamp | 60 days; 7–90 |
| Age field | `occurred_at` only |
| Scheduler | WooCommerce Action Scheduler daily; batched (~100) |
| Scope | Active and suppressed equally |

## HPOS

Compatibility declaration retained; capture/privacy use public WC order APIs only. Integration suite asserts `custom_order_tables` compatibility.

## Tests and local validation

Host has no system PHP; gates ran in Docker (`ugeo-php8.3-mysqli` + MariaDB 11.4).

| Gate | Result |
|------|--------|
| `php vendor/bin/phpcs` | PASS |
| `scripts/ci/check.sh` | PASS |
| Unit | PASS — 13 tests, 38 assertions |
| Integration | PASS — 19 tests, 2433 assertions |

## DEV verification

**Not performed on DEV WordPress.** USP is still not bind-mounted in `apps/wordpress/compose.yml`. Automated WooCommerce 11.0.1 integration coverage is the M1 evidence substitute. Adding a VPS compose mount is out of milestone scope.

## CI

PR #2 GitHub Actions: all jobs SUCCESS (lint/PHPCS, unit 8.1/8.3/8.4, integration PHP 8.3 / WC 11.0.1). Local gates were green before merge.

## Architecture / ADR updates

- ADR-0004 **Accepted** with fail-closed resolver + WC 11.0.1 evidence
- ADR-0006 quantity `DECIMAL(18,6)` note
- ADR-0007 **Accepted** prospective dual-path + retrospective limitation
- FROZEN.md not rewritten for milestone detail
- Plan: `docs/milestones/M1-CAPTURE-STORAGE-PLAN.md`

## Explicit M2+ non-delivery

Confirmed absent: notification REST, SelectionEngine, toaster JS/CSS, templates/tokens, UGC, visitor-country weighting, settings/diagnostics UI, fake/custom purchase events, region/city columns.

## Known limitations / M2 notes

1. WC default `woocommerce_stock_amount` → `intval`; fractional qty stores correctly when float stock amounts are enabled (tested with `floatval` filter).
2. `date_created` is nearly always present on WC orders; fail-closed path is implemented; integration soft-documents when WC always supplies created.
3. Retrospective privacy after prior WC anonymization without USP erasure remains unrecoverable by design.
4. No CLI historical backfill in M1 (`CaptureService::capture_order` is reusable later).

## Release closure (post-merge)

1. PR #2 merged to `main` as `0151f1d5d679bfbc89f3a7b4b0489fbef7a0222d`
2. Annotated tag `v0.1.0` created on that merge commit and pushed
3. Tag verified: `git rev-parse v0.1.0^{}` = merge SHA
4. M1 recorded **CLOSED**

Next milestone: **M2** (`0.2.0`) — not started in this release step.

## Working tree

Clean on `main` after this release-record commit (except as noted at commit time).
