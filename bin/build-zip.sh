#!/usr/bin/env bash
# bin/build-zip.sh
#
# Produces a dash-dolphin.zip ready for upload to WordPress.org or manual
# install. With --staging, injects a dd_api_base_url filter that points the
# plugin at the staging Supabase project, so the staging zip is plug-and-play
# without a wp-config.php snippet.
#
# Usage:
#   bin/build-zip.sh                # production zip -> dash-dolphin.zip
#   bin/build-zip.sh --staging      # staging zip    -> dash-dolphin-staging.zip
#
# The staging filter is injected between two sentinel markers in
# dash-dolphin/dash-dolphin.php:
#   // === STAGING BUILD: auto-injected filters ... ===
#   // === END STAGING BUILD ===
# Production builds leave that block empty.

set -e

FLAVOR="prod"
if [ "${1:-}" = "--staging" ]; then
    FLAVOR="staging"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_DIR="${ROOT_DIR}/dash-dolphin"
PLUGIN_FILE="${PLUGIN_DIR}/dash-dolphin.php"

if [ "${FLAVOR}" = "staging" ]; then
    ZIP_FILE="${ROOT_DIR}/dash-dolphin-staging.zip"
else
    ZIP_FILE="${ROOT_DIR}/dash-dolphin.zip"
fi

STAGING_SUPABASE_URL="https://dcunazzebgjqpjzikzqb.supabase.co"
START_MARK="// === STAGING BUILD: auto-injected filters (registered BEFORE the define so the filter actually applies) ==="
END_MARK="// === END STAGING BUILD ==="

echo "Building ${ZIP_FILE} (flavor: ${FLAVOR})..."

# 1. Clean any existing zip.
if [ -f "${ZIP_FILE}" ]; then
    echo "Removing existing ${ZIP_FILE}"
    rm -f "${ZIP_FILE}"
fi

# 2. Optional composer install for runtime deps. We only run composer if it's
#    actually available; the plugin has no runtime composer dependencies today
#    so the absence of composer should not fail the build.
if [ -f "${ROOT_DIR}/composer.json" ] && command -v composer >/dev/null 2>&1; then
    echo "Running composer install --no-dev..."
    (cd "${ROOT_DIR}" && composer install --no-dev --no-progress --quiet)
fi

# 3. If staging, inject the dd_api_base_url filter between the sentinels.
#    We work on a copy and restore the original on exit so the working tree
#    is never left in a half-mutated state.
RESTORE_FILE=""
cleanup() {
    if [ -n "${RESTORE_FILE}" ] && [ -f "${RESTORE_FILE}" ]; then
        mv "${RESTORE_FILE}" "${PLUGIN_FILE}"
    fi
}
trap cleanup EXIT

if [ "${FLAVOR}" = "staging" ]; then
    if ! grep -qF "${START_MARK}" "${PLUGIN_FILE}"; then
        echo "ERROR: start sentinel not found in ${PLUGIN_FILE}" >&2
        exit 1
    fi
    if ! grep -qF "${END_MARK}" "${PLUGIN_FILE}"; then
        echo "ERROR: end sentinel not found in ${PLUGIN_FILE}" >&2
        exit 1
    fi

    RESTORE_FILE="$(mktemp)"
    cp "${PLUGIN_FILE}" "${RESTORE_FILE}"

    # Python is the most portable way to do a multi-line, between-sentinel
    # replacement here without worrying about sed dialect differences (BSD vs
    # GNU). Pass everything as env to dodge quoting issues.
    STAGING_URL="${STAGING_SUPABASE_URL}" \
    PLUGIN_FILE="${PLUGIN_FILE}" \
    START_MARK="${START_MARK}" \
    END_MARK="${END_MARK}" \
    python3 - <<'PY'
import os
import re

path = os.environ["PLUGIN_FILE"]
start = os.environ["START_MARK"]
end = os.environ["END_MARK"]
url = os.environ["STAGING_URL"]

with open(path, "r", encoding="utf-8") as fh:
    src = fh.read()

block = (
    f"{start}\n"
    f"add_filter( 'dd_api_base_url', function () {{\n"
    f"\treturn '{url}';\n"
    f"}} );\n"
    f"{end}"
)

pattern = re.compile(
    re.escape(start) + r".*?" + re.escape(end),
    re.DOTALL,
)
new_src, n = pattern.subn(block, src, count=1)
if n != 1:
    raise SystemExit("Sentinel block not matched exactly once")

with open(path, "w", encoding="utf-8") as fh:
    fh.write(new_src)

print("Injected staging filter between sentinels.")
PY

    echo "Verifying staging filter is present..."
    if ! grep -qF "${STAGING_SUPABASE_URL}" "${PLUGIN_FILE}"; then
        echo "ERROR: staging URL missing from ${PLUGIN_FILE} after injection" >&2
        exit 1
    fi
fi

# 4. Zip dash-dolphin/ into the target zip, excluding dev/build artifacts.
cd "${ROOT_DIR}"
zip -r "${ZIP_FILE}" dash-dolphin/ \
    --exclude "*.git*" \
    --exclude "*/node_modules/*" \
    --exclude "*/tests/*" \
    --exclude "*.test.php" \
    --exclude "*/composer.lock" \
    --exclude "*/.DS_Store" \
    --exclude "*.md" \
    --exclude "*/vendor/*"

# readme.txt must be included (excluded *.md not *.txt, so it's fine).
zip -u "${ZIP_FILE}" dash-dolphin/readme.txt 2>/dev/null || true

# 5. Print final zip size and path.
ZIP_SIZE=$(du -sh "${ZIP_FILE}" | cut -f1)
echo ""
echo "Build complete."
echo "  Flavor: ${FLAVOR}"
echo "  Path:   ${ZIP_FILE}"
echo "  Size:   ${ZIP_SIZE}"
