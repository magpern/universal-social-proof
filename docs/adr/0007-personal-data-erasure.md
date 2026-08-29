# ADR-0007 — HPOS-safe personal-data erasure

## Status

**Accepted** (M1 evidence — WooCommerce 11.0.1)

## Context

`usp_events` retains `source_order_id` for lifecycle integrity. WordPress personal-data requests are keyed by email/user identity; USP has no email column and cannot map identity → rows alone.

## Decision

### Prospective dual path

1. **WordPress eraser** (`PersonalDataEraser`), registered on `wp_privacy_personal_data_erasers` at **priority 5** (before WooCommerce’s default erase priority 10): paginated  
   `wc_get_orders( [ 'limit' => 10, 'page' => $page, 'customer' => [ $email, $user_id? ] ] )`  
   then hard-delete USP rows by those `source_order_id` values.
2. **Woo pre-anonymization hook** `woocommerce_privacy_before_remove_order_personal_data`: while the live `WC_Order` is still available, hard-delete USP rows for `$order->get_id()`.

### Exporter

Minimal WordPress exporter: occurrence time, country, original quantity, public event UUID. Does **not** export internal DB id, `source_order_id`, or `source_item_id`.

### No PII in USP storage

Do not add email, customer id, name, or address to `usp_events` to ease lookup.

### Explicit limitation (retrospective)

**Prospective erasure is supported** while the identifying WooCommerce relationship remains recoverable (USP eraser and/or Woo pre-anonymization hook).

**USP cannot retrospectively reconstruct association** after WooCommerce has already irreversibly anonymized or deleted the email/`customer_id` relationship and USP did not previously erase those rows (for example, USP installed later). In that case USP **fails closed**: no email-column scan of `usp_events`.

## Consequences

Erasure correctness depends on recoverable WC order identity at erasure time. The no-PII event model is preserved.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §7 · ADR-0003 · [milestones/M1-CAPTURE-STORAGE-PLAN.md](../milestones/M1-CAPTURE-STORAGE-PLAN.md)
