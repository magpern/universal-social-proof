#!/usr/bin/env bash
# CI policy checks for Universal Social Proof (M7 candidate).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail() { echo "FAIL: $*" >&2; exit 1; }

echo "==> PHP lint"
composer lint

echo "==> Required docs/files"
test -f docs/architecture/FROZEN.md || fail "missing FROZEN.md"
test -f docs/milestones/M7-V1-HARDENING-RELEASE-PLAN.md || fail "missing M7 plan"
test -f docs/milestones/M6-M7-V1-PROGRAM.md || fail "missing M6/M7 program"
test -f docs/milestones/M6-ADMIN-DIAGNOSTICS-PLAN.md || fail "missing M6 plan"
test -f docs/milestones/M5-GEOGRAPHY-UGC-PLAN.md || fail "missing M5 plan"
test -f uninstall.php || fail "missing uninstall.php"
grep -q 'Plugin Name: Universal Social Proof' universal-social-proof.php || fail "plugin header name"
grep -q 'Version: 1.0.1' universal-social-proof.php || fail "expected version 1.0.1"
grep -q "define( 'USP_VERSION', '1.0.1' )" universal-social-proof.php || fail "USP_VERSION constant"
grep -q 'Stable tag: 1.0.1' readme.txt || fail "Stable tag must be 1.0.1"
grep -q 'namespace UniversalSocialProof' src/Plugin.php || fail "namespace"
grep -q 'uninstall.php' scripts/build-release-package.sh || fail "build-release-package must INCLUDE uninstall.php"

echo "==> Asset size budgets"
js_size=$(wc -c < assets/js/usp-toaster.js)
css_size=$(wc -c < assets/css/usp-toaster.css)
test "$js_size" -le 16384 || fail "usp-toaster.js exceeds 16 KiB ($js_size bytes)"
test "$css_size" -le 6144 || fail "usp-toaster.css exceeds 6 KiB ($css_size bytes)"
echo "JS=${js_size}B CSS=${css_size}B"

echo "==> M7 packages present"
test -d src/Template || fail "missing src/Template"
test -d src/Targeting || fail "missing src/Targeting"
test -d src/Geo || fail "missing src/Geo"
test -d src/Admin || fail "missing src/Admin"
test -d src/Settings || fail "missing src/Settings"

echo "==> Forbidden symbols (fake; no client country REST authority)"
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
  fail "legacy single-key template/exclusion options must not be read from Template/Targeting"
fi

if grep -nE "get_option\s*\(\s*['\"]usp_geo|update_option\s*\(\s*['\"]usp_geo|add_option\s*\(\s*['\"]usp_geo" src 2>/dev/null | grep -q .; then
  fail "persisted usp_geo_* options are forbidden (use usp_settings)"
fi

if grep -nE "REMOTE_ADDR|HTTP_X_FORWARDED_FOR|CF-IPCountry|HTTP_CF_IPCOUNTRY|geolocation" src/Geo src/Selection src/Rest 2>/dev/null | grep -q .; then
  fail "USP must not implement IP/header geolocation"
fi

if grep -nE "universal_geo_get_region_code" src 2>/dev/null | grep -q .; then
  fail "must not call universal_geo_get_region_code"
fi

if grep -nE "'country'\s*=>" src/Rest/NotificationsController.php 2>/dev/null | grep -q .; then
  fail "client country REST parameter is forbidden"
fi

if grep -nE "'visitor_country'|'region'|'city'" src/Rest/NotificationsController.php 2>/dev/null | grep -q .; then
  fail "public DTO / REST must not expose visitor geo fields"
fi

if ! grep -q "'public_id'" src/Rest/NotificationsController.php || ! grep -q "ALLOWLIST" src/Rest/NotificationsController.php; then
  fail "notifications ALLOWLIST missing"
fi

if grep -nEi 'source_order_id|source_item_id' src/Admin/DiagnosticsService.php 2>/dev/null | grep -q .; then
  fail "diagnostics must not reference provenance columns"
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
grep -q '## \[1\.0\.1\]' CHANGELOG.md || fail "CHANGELOG missing 1.0.1 section"
grep -q '## \[1\.0\.0\]' CHANGELOG.md || fail "CHANGELOG missing 1.0.0 section"
grep -q '## \[0\.6\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.6.0 section"
grep -q '## \[0\.5\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.5.0 section"

echo "==> No event schema version bump in M7"
grep -q "DB_VERSION = '20260829m1'" src/Storage/Schema.php || fail "M7 must not bump usp_db_version"
if grep -nE "ALTER TABLE|ADD COLUMN|ADD KEY|ADD INDEX" src/Admin src/Settings src/Template src/Frontend 2>/dev/null | grep -q .; then
  fail "M7 must not alter event schema from Admin/Settings/Template/Frontend"
fi

echo "==> M7 hardening markers"
grep -q 'plain_text' src/Template/TemplateRenderer.php || fail "TemplateRenderer must sanitize token values as plain text"
grep -q 'Dismiss notification' src/Frontend/ShellRenderer.php || fail "ShellRenderer must expose dismiss accessible name"
grep -q 'manage_woocommerce' src/Admin/DiagnosticsService.php || fail "DiagnosticsService must gate on manage_woocommerce"
grep -q 'sanitize_context' src/Logger.php || fail "Logger must sanitize context"

echo "==> JS tests (when node available)"
if command -v node >/dev/null 2>&1; then
  node --test tests/js/usp-toaster.test.cjs
else
  echo "node absent in this environment; JS tests run in CI / Docker"
fi

echo "==> All M7 CI checks passed"
