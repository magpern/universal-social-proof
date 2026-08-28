# ADR-0012 — Admin capability and menu placement

## Status

Accepted (architecture freeze)

## Context

Universal plugins split between `manage_options` (UGC/USA) and `manage_woocommerce` under WooCommerce (UPR). USP is WooCommerce-domain.

## Decision

- Admin capability: **`manage_woocommerce`**.
- Menu: **submenu under WooCommerce** (same pattern as Universal Product Reviews).
- Not a new custom capability model in v1.

## Consequences

Shop managers with WooCommerce caps can configure USP; aligns with UPR rather than UGC’s top-level `manage_options` menu.

## Related

[architecture/FROZEN.md](../architecture/FROZEN.md) §2, §12, §17
