# ADR-0019 — Appearance and custom CSS

## Status

Accepted (v1.1)

## Context

The v1.0 toaster is functional but visually plain. Operators need bounded
appearance controls plus advanced custom CSS without a page builder.

## Decision

1. Ship a polished default toast (original USP design; not a copy of third-party CSS).
2. Persist appearance in `usp_settings` (settings version 2): position, colors,
   radius, shadow, image/close toggles, custom CSS.
3. Apply via CSS custom properties on `.usp-toaster` plus a small stable class contract.
4. Custom CSS: `manage_woocommerce` + nonce; max length 4000; output only when
   USP frontend loads; treat as trusted administrator CSS (same trust class as
   theme Customizer CSS). No PHP/HTML/JS execution path.
5. Accessibility defaults remain: keyboard dismiss, live region, reduced motion.

## Consequences

BootstrapConfig/ShellRenderer emit appearance variables. Malicious CSS from a
compromised WooCommerce admin remains an admin-capability risk, documented.

## Related

[V1.1-FEATURE-PLAN.md](../releases/V1.1-FEATURE-PLAN.md) ·
[0015-settings-storage-precedence.md](0015-settings-storage-precedence.md)
