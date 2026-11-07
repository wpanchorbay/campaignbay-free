#!/bin/bash
set -e

# Define the zip filename
ZIP_NAME="campaignbay.zip"
PLUGIN_SLUG="campaignbay"

# Build the admin SPA (production + legacy) before packaging, so the zip
# never ships a stale build/ directory.
VERSION=$(grep -m1 '^ \* Version:' campaignbay.php | sed -E 's/.*Version:[[:space:]]*//')
echo "Building version $VERSION..."
./build.sh "$VERSION"

# Cleanup previous build if exists
rm -rf dist
rm -f $ZIP_NAME

# Helper function to copy plugin files to a target path
copy_plugin_files() {
    local DEST=$1
    echo "Copying files to $DEST..."

    # Copy folders
    cp -r app "$DEST/"
    cp -r languages "$DEST/"
    cp -r assets "$DEST/"
    cp -r build "$DEST/"
    cp -r includes "$DEST/"

    # vendor/ was never copied, so the released zip contained no
    # deactivation-feedback package at all and class_exists() was always false.
    cp -r vendor "$DEST/"

    # Strip dev-only metadata from vendored packages (avoids "hidden files" flags).
    find "$DEST/vendor" \( -name '.gitignore' -o -name '.gitattributes' -o -name '.editorconfig' \
        -o -name 'phpcs.xml' -o -name 'phpunit.xml*' -o -name '.php-cs-fixer*' \) -delete 2>/dev/null || true
    find "$DEST/vendor" -type d \( -name 'tests' -o -name 'test' -o -name 'docs' -o -name '.github' \
        -o -name 'examples' \) -exec rm -rf {} + 2>/dev/null || true

    # Copy files
    cp index.php "$DEST/"
    cp readme.txt "$DEST/"
    cp uninstall.php "$DEST/"
    cp campaignbay.php "$DEST/"
}

# Create a temporary directory for staging
mkdir -p dist/zip-staging
copy_plugin_files dist/zip-staging

# Create the zip file
echo "Creating zip file..."
cd dist/zip-staging
zip -r ../../$ZIP_NAME .
cd ../..
rm -rf dist/zip-staging

# dist/svn/trunk mirrors the real WordPress.org SVN layout (trunk/ IS the
# plugin root, no plugin-slug wrapper) -- rsync straight into an svn working
# copy's trunk/ for `svn commit`. Left as a plain folder on purpose (not
# zipped, not removed after) since that's how svn expects it.
mkdir -p dist/svn/trunk
copy_plugin_files dist/svn/trunk

echo "Done! Created $ZIP_NAME containing:"
echo "- app/"
echo "- languages/"
echo "- assets/"
echo "- build/"
echo "- index.php"
echo "- readme.txt"
echo "- uninstall.php"
echo "- campaignbay.php"
echo ""
echo "Also created dist/svn/trunk/ (uncompressed, ready for \`svn commit\` into a checkout's trunk/)"
