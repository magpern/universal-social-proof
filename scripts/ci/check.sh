#!/usr/bin/env bash
# CI policy checks for Universal Social Proof (M5).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail() { echo "FAIL: $*" >&2; exit 1; }

echo "==> PHP lint"
composer lint

echo "==> Required docs/files"
test -f docs/architecture/FROZEN.md || fail "missing FROZEN.md"
test -f docs/milestones/M5-GEOGRAPHY-UGC-PLAN.md || fail "missing M5 plan"
test -f docs/milestones/M4-TEMPLATES-TARGETING-PLAN.md || fail "missing M4 plan"
test -f docs/milestones/M3-STOREFRONT-TOASTER-PLAN.md || fail "missing M3 plan"
grep -q 'Plugin Name: Universal Social Proof' universal-social-proof.php || fail "plugin header name"
grep -q 'Version: 0.5.0' universal-social-proof.php || fail "expected version 0.5.0"
grep -q "define( 'USP_VERSION', '0.5.0' )" universal-social-proof.php || fail "USP_VERSION constant"
grep -q 'namespace UniversalSocialProof' src/Plugin.php || fail "namespace"

echo "==> Asset size budgets"
js_size=$(wc -c < assets/js/usp-toaster.js)
css_size=$(wc -c < assets/css/usp-toaster.css)
test "$js_size" -le 16384 || fail "usp-toaster.js exceeds 16 KiB ($js_size bytes)"
test "$css_size" -le 6144 || fail "usp-toaster.css exceeds 6 KiB ($css_size bytes)"
echo "JS=${js_size}B CSS=${css_size}B"

echo "==> M5 packages present; M6 Admin absent"
test -d src/Template || fail "missing src/Template"
test -d src/Targeting || fail "missing src/Targeting"
test -d src/Geo || fail "missing src/Geo"
if [ -d src/Admin ]; then
  fail "forbidden M6 package directory: src/Admin"
fi

echo "==> Forbidden symbols (Admin/fake; no client country REST authority)"
SCAN_FILES=()
while IFS= read -r -d '' f; do
  SCAN_FILES+=( "$f" )
done < <(find src universal-social-proof.php -name '*.php' -print0 2>/dev/null)

forbid_re='fake.?purchase|Fabricat'
if printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null | grep -q .; then
  printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null || true
  fail "forbidden fake symbols detected"
fi

if grep -nE "get_option\s*\(\s*['\"]usp_notification_template|get_option\s*\(\s*['\"]usp_excluded_product" src/Template src/Targeting 2>/dev/null | grep -q .; then
  fail "persisted M4 template/exclusion options are forbidden"
fi

if grep -nE "get_option\s*\(\s*['\"]usp_geo|update_option\s*\(\s*['\"]usp_geo|add_option\s*\(\s*['\"]usp_geo" src 2>/dev/null | grep -q .; then
  fail "persisted M5 geo options are forbidden"
fi

if grep -nE "REMOTE_ADDR|HTTP_X_FORWARDED_FOR|CF-IPCountry|HTTP_CF_IPCOUNTRY|geolocation" src/Geo src/Selection src/Rest 2>/dev/null | grep -q .; then
  fail "USP must not implement IP/header geolocation"
fi

if grep -nE "universal_geo_get_region_code" src 2>/dev/null | grep -q .; then
  fail "M5 must not call universal_geo_get_region_code"
fi

# REST must not register a client country query parameter.
if grep -nE "'country'\s*=>" src/Rest/NotificationsController.php 2>/dev/null | grep -q .; then
  fail "client country REST parameter is forbidden"
fi

# Public DTO allowlist must not include visitor_country / geo leakage fields.
if grep -nE "'visitor_country'|'region'|'city'" src/Rest/NotificationsController.php 2>/dev/null | grep -q .; then
  fail "public DTO / REST must not expose visitor geo fields"
fi

if ! grep -q "'public_id'" src/Rest/NotificationsController.php || ! grep -q "ALLOWLIST" src/Rest/NotificationsController.php; then
  fail "notifications ALLOWLIST missing"
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
grep -q '## \[0\.5\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.5.0 section"
grep -q '## \[0\.4\.1\]' CHANGELOG.md || fail "CHANGELOG missing 0.4.1 section"
grep -q '## \[0\.4\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.4.0 section"
grep -q '## \[0\.3\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.3.0 section"

echo "==> No schema version bump in M5"
grep -q "DB_VERSION = '20260829m1'" src/Storage/Schema.php || fail "M5 must not bump usp_db_version"
if grep -nE "status_product_country|CREATE TABLE|dbDelta" src/Geo src/Selection 2>/dev/null | grep -q .; then
  fail "M5 must not introduce schema migration in Geo/Selection"
fi

echo "==> JS tests (when node available)"
if command -v node >/dev/null 2>&1; then
  node --test tests/js/usp-toaster.test.cjs
else
  echo "node absent in this environment; JS tests run in CI / Docker"
fi

echo "==> All M5 CI checks passed"
