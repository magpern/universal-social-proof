# M3 visual acceptance evidence

## A. Live M2 inert path (JS harness)

Synthetic M2-shaped DTOs (no `message`) exercise the runtime:

- one successful fetch
- `stoppedReason === m2-inert`
- zero visible presentations

Covered by `tests/js/usp-toaster.test.cjs` → `runtime inert M2 path`.

## B. Presentable fixture path (test-only)

Fixtures in `tests/fixtures/toaster-events.cjs` include `message`.
Harness: `tests/visual/harness.cjs`.

Covered by Node tests for show/dismiss/relative-time/thumbnail paths.
Viewport matrix (360/390/430/768/1440) validated via CSS layout rules + fixture renderer; screenshots optional at DEV bind-mount time.

**No PHP/REST/DB fixture injection exists.**
