#!/usr/bin/env bash
# M0 CI checks — lint, structure, and M1+ exclusion guards.
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
  # Host/Docker PHP images may lack the Composer CLI; still reject invalid JSON/metadata.
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
test -f src/WooCommerce/WooCommerceGate.php || fail "missing WooCommerceGate"
test -f docs/architecture/FROZEN.md || fail "missing architecture freeze"
test -f docs/milestones/M0-FOUNDATION-PLAN.md || fail "missing M0 plan"
grep -q 'Plugin Name: Universal Social Proof' universal-social-proof.php || fail "plugin header name"
grep -q 'Version: 0.0.0' universal-social-proof.php || fail "expected version 0.0.0"
grep -q "define( 'USP_VERSION', '0.0.0' )" universal-social-proof.php || fail "USP_VERSION constant"
grep -q 'namespace UniversalSocialProof' src/Plugin.php || fail "namespace"
grep -q 'Text Domain: universal-social-proof' universal-social-proof.php || fail "text domain"
grep -q 'Requires Plugins: woocommerce' universal-social-proof.php || fail "Requires Plugins"

echo "==> M0 must not pre-create feature packages"
for dir in Capture Storage Selection Rest Template Frontend Geo Privacy Cleanup Admin; do
  if [ -d "src/$dir" ]; then
    fail "forbidden M0 package directory: src/$dir"
  fi
done

echo "==> M1+ exclusion scan (src + main file)"
SCAN_FILES=()
while IFS= read -r -d '' f; do
  SCAN_FILES+=( "$f" )
done < <(find src universal-social-proof.php -name '*.php' -print0 2>/dev/null)

forbid_re='usp_events|source_order_id|source_item_id|public_id|occurred_at|captured_at|register_rest_route|woocommerce_order_status_|wp_enqueue_script|wp_enqueue_style|\{\{product\}\}|\{\{country\}\}|\{\{time_ago\}\}|\{\{quantity\}\}|GeoContextAdapter|fake.?purchase|Fabricat'
if printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null | grep -q .; then
  printf '%s\0' "${SCAN_FILES[@]}" | xargs -0 grep -nE "$forbid_re" 2>/dev/null || true
  fail "forbidden M1+ symbols detected in M0 foundation"
fi

echo "==> No frontend asset directories"
for d in assets dist build public/js public/css; do
  if [ -d "$d" ]; then
    fail "unexpected frontend asset directory: $d"
  fi
done

echo "==> Changelog version agreement"
grep -q '## \[0\.0\.0\]' CHANGELOG.md || fail "CHANGELOG missing 0.0.0 section"

echo "==> All M0 CI checks passed"
