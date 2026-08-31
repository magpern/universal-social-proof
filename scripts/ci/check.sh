#!/usr/bin/env bash
# CI policy checks for Universal Social Proof (M4).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail() { echo "FAIL: $*" >&2; exit 1; }

echo "==> PHP lint"
composer lint

echo "==> Required docs/files"
test -f docs/architecture/FROZEN.md || fail "missing FROZEN.md"
test -f docs/milestones/M4-TEMPLATES-TARGETING-PLAN.md || fail "missing M4 plan"
test -f docs/milestones/M3-STOREFRONT-TOASTER-PLAN.md || fail "missing M3 plan"
grep -q 'Plugin Name: Universal Social Proof' universal-social-proof.php || fail "plugin header name"
grep -q 'Version: 0.4.0' universal-social-proof.php || fail "expected version 0.4.0"
grep -q "define( 'USP_VERSION', '0.4.0' )" universal-social-proof.php || fail "USP_VERSION constant"
grep -q 'namespace UniversalSocialProof' src/Plugin.php || fail "namespace"

echo "==> Asset size budgets"
js_size=$(wc -c < assets/js/usp-toaster.js)
css_size=$(wc -c < assets/css/usp-toaster.css)
test "$js_size" -le 16384 || fail "usp-toaster.js exceeds 16 KiB ($js_size bytes)"
test "$css_size" -le 6144 || fail "usp-toaster.css exceeds 6 KiB ($css_size bytes)"
echo "JS=${js_size}B CSS=${css_size}B"

echo "==> M4 packages present; M5/M6 packages absent"
test -d src/Template || fail "missing src/Template"
test -d src/Targeting || fail "missing src/Targeting"
for dir in Geo Admin; do
  if [ -d "src/$dir" ]; then
    fail "forbidden M5/M6 package directory: src/$dir"
  fi
done

echo "==> Forbidden symbols (Geo/Admin/fake; no premature options)"
SCAN_FILES=()
while IFS= read -r -d '' f; do
  SCAN_FILES+=( "$f" )
done < <(find src universal-social-proof.php -name '*.php' -print0 2>/dev/null)

forbid_re='GeoContextAdapter|fake.?purchase|Fabricat'
if printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null | grep -q .; then
  printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null || true
  fail "forbidden M5+/fake symbols detected"
fi

if grep -nE "get_option\s*\(\s*['\"]usp_notification_template|get_option\s*\(\s*['\"]usp_excluded_product" src/Template src/Targeting 2>/dev/null | grep -q .; then
  fail "persisted M4 template/exclusion options are forbidden"
fi

echo "==> No PHP fixture injection in Frontend"
if grep -nEi 'fixture|WP_DEBUG|fake.?notification|synthetic' src/Frontend/*.php 2>/dev/null | grep -q .; then
  grep -nEi 'fixture|WP_DEBUG|fake.?notification|synthetic' src/Frontend/*.php || true
  fail "Frontend PHP must not inject fixtures"
fi

echo "==> No unexpected frontend build dirs"
for d in dist build public/js public/css; do
  if [ -d "$d" ]; then
    fail "unexpected frontend build directory: $d"
  fi
done

echo "==> Changelog version agreement"
grep -q '## \[0\.4\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.4.0 section"
grep -q '## \[0\.3\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.3.0 section"

echo "==> No schema version bump in M4"
grep -q "DB_VERSION = '20260829m1'" src/Storage/Schema.php || fail "M4 must not bump usp_db_version"

echo "==> JS tests (when node available)"
if command -v node >/dev/null 2>&1; then
  node --test tests/js/usp-toaster.test.cjs
else
  echo "node absent in this environment; JS tests run in CI / Docker"
fi

echo "==> All M4 CI checks passed"
