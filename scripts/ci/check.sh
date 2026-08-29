#!/usr/bin/env bash
# M1 CI checks — lint, structure, and M2+ exclusion guards.
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
test -f docs/milestones/M1-CAPTURE-STORAGE-PLAN.md || fail "missing M1 plan"
grep -q 'Plugin Name: Universal Social Proof' universal-social-proof.php || fail "plugin header name"
grep -q 'Version: 0.1.0' universal-social-proof.php || fail "expected version 0.1.0"
grep -q "define( 'USP_VERSION', '0.1.0' )" universal-social-proof.php || fail "USP_VERSION constant"
grep -q 'namespace UniversalSocialProof' src/Plugin.php || fail "namespace"

echo "==> M2+ packages must not exist"
for dir in Selection Rest Template Frontend Geo Admin; do
  if [ -d "src/$dir" ]; then
    fail "forbidden M2+ package directory: src/$dir"
  fi
done

echo "==> M2+ exclusion scan (src + main file)"
SCAN_FILES=()
while IFS= read -r -d '' f; do
  SCAN_FILES+=( "$f" )
done < <(find src universal-social-proof.php -name '*.php' -print0 2>/dev/null)

forbid_re='register_rest_route|SelectionEngine|\{\{product\}\}|\{\{country\}\}|\{\{time_ago\}\}|\{\{quantity\}\}|GeoContextAdapter|fake.?purchase|Fabricat|wp_enqueue_script|wp_enqueue_style'
if printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null | grep -q .; then
  printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null || true
  fail "forbidden M2+ symbols detected"
fi

echo "==> No frontend asset directories"
for d in assets dist build public/js public/css; do
  if [ -d "$d" ]; then
    fail "unexpected frontend asset directory: $d"
  fi
done

echo "==> Changelog version agreement"
grep -q '## \[0\.1\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.1.0 section"

echo "==> All M1 CI checks passed"
