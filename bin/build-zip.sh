#!/usr/bin/env bash
# bin/build-zip.sh
# Produces dash-dolphin.zip ready for upload to WordPress.org or manual install.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_DIR="${ROOT_DIR}/dash-dolphin"
ZIP_FILE="${ROOT_DIR}/dash-dolphin.zip"

echo "Building dash-dolphin.zip..."

# 1. Clean any existing zip.
if [ -f "${ZIP_FILE}" ]; then
    echo "Removing existing ${ZIP_FILE}"
    rm -f "${ZIP_FILE}"
fi

# 2. Run composer install --no-dev if composer.json has runtime deps (defensive).
if [ -f "${ROOT_DIR}/composer.json" ]; then
    echo "Running composer install --no-dev..."
    cd "${ROOT_DIR}"
    composer install --no-dev --no-progress --quiet
fi

# 3. Zip dash-dolphin/ into dash-dolphin.zip, excluding dev/build artifacts.
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
# If readme.txt was accidentally excluded, re-add it:
zip -u "${ZIP_FILE}" dash-dolphin/readme.txt 2>/dev/null || true

# 4. Print final zip size and path.
ZIP_SIZE=$(du -sh "${ZIP_FILE}" | cut -f1)
echo ""
echo "Build complete."
echo "  Path: ${ZIP_FILE}"
echo "  Size: ${ZIP_SIZE}"
