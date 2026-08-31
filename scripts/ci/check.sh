#!/usr/bin/env bash
# M3 CI checks — lint, structure, size budgets, and M4+ exclusion guards.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail() {
  echo "CI CHECK FAILED: $*" >&2
  exit 1
}

echo "==> PHP syntax lint"
find . -name '*.php' -not -path './vendor/*' -not -path './tests/tmp/*' -print0 | while IFS= read -r -d '' f; do
  php -l "$f" >/dev/null
done

echo "==> Composer validate"
if command -v composer >/dev/null 2>&1; then
  composer validate --no-check-publish --strict
else
  php -r '
    $raw = file_get_contents("composer.json");
    if (false === $raw) { fwrite(STDERR, "missing composer.json\n"); exit(1); }
    $json = json_decode($raw, true);
    if (!is_array($json)) { fwrite(STDERR, "composer.json is not valid JSON\n"); exit(1); }
    foreach (array("name", "require", "autoload") as $key) {
      if (!array_key_exists($key, $json)) { fwrite(STDERR, "composer.json missing {$key}\n"); exit(1); }
    }
    if (($json["name"] ?? "") !== "magpern/universal-social-proof") {
      fwrite(STDERR, "unexpected composer package name\n"); exit(1);
    }
    echo "composer.json structural check OK (composer CLI absent)\n";
  '
fi

echo "==> Structure"
test -f universal-social-proof.php || fail "missing plugin bootstrap"
test -f src/Plugin.php || fail "missing src/Plugin.php"
test -f src/Storage/Schema.php || fail "missing Schema"
test -f src/Capture/CaptureService.php || fail "missing CaptureService"
test -d src/Selection || fail "missing src/Selection"
test -d src/Product || fail "missing src/Product"
test -d src/Rest || fail "missing src/Rest"
test -d src/Frontend || fail "missing src/Frontend"
test -f src/Frontend/FrontendController.php || fail "missing FrontendController"
test -f src/Rest/NotificationsController.php || fail "missing NotificationsController"
test -f assets/js/usp-toaster.js || fail "missing toaster JS"
test -f assets/css/usp-toaster.css || fail "missing toaster CSS"
test -f docs/milestones/M2-SELECTION-REST-PLAN.md || fail "missing M2 plan"
test -f docs/milestones/M3-STOREFRONT-TOASTER-PLAN.md || fail "missing M3 plan"
grep -q 'Plugin Name: Universal Social Proof' universal-social-proof.php || fail "plugin header name"
grep -q 'Version: 0.3.0' universal-social-proof.php || fail "expected version 0.3.0"
grep -q "define( 'USP_VERSION', '0.3.0' )" universal-social-proof.php || fail "USP_VERSION constant"
grep -q 'namespace UniversalSocialProof' src/Plugin.php || fail "namespace"

echo "==> Asset size budgets"
js_size=$(wc -c < assets/js/usp-toaster.js)
css_size=$(wc -c < assets/css/usp-toaster.css)
test "$js_size" -le 16384 || fail "usp-toaster.js exceeds 16 KiB ($js_size bytes)"
test "$css_size" -le 6144 || fail "usp-toaster.css exceeds 6 KiB ($css_size bytes)"
echo "JS=${js_size}B CSS=${css_size}B"

echo "==> M4+ packages must not exist"
for dir in Template Geo Admin; do
  if [ -d "src/$dir" ]; then
    fail "forbidden M4+ package directory: src/$dir"
  fi
done

echo "==> M4+ exclusion scan (src + main file)"
SCAN_FILES=()
while IFS= read -r -d '' f; do
  SCAN_FILES+=( "$f" )
done < <(find src universal-social-proof.php -name '*.php' -print0 2>/dev/null)

forbid_re='\{\{product\}\}|\{\{country\}\}|\{\{location\}\}|\{\{time_ago\}\}|\{\{quantity\}\}|GeoContextAdapter|fake.?purchase|Fabricat'
if printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null | grep -q .; then
  printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null || true
  fail "forbidden M4+ symbols detected"
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
grep -q '## \[0\.3\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.3.0 section"
grep -q '## \[0\.2\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.2.0 section"
grep -q '## \[0\.1\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.1.0 section"

echo "==> No schema version bump in M3"
grep -q "DB_VERSION = '20260829m1'" src/Storage/Schema.php || fail "M3 must not bump usp_db_version"

echo "==> JS tests (when node available)"
if command -v node >/dev/null 2>&1; then
  node --test tests/js/usp-toaster.test.cjs
else
  echo "node absent in this environment; JS tests run in CI / Docker"
fi

echo "==> All M3 CI checks passed"
