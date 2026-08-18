#!/bin/bash

# Define the zip filename
ZIP_NAME="campaignbay.zip"
PLUGIN_SLUG="campaignbay"

# Cleanup previous build if exists
rm -rf dist
rm -f $ZIP_NAME

# Create a temporary directory for staging
mkdir -p dist

# Copy folders
echo "Copying folders..."
cp -r app dist/
cp -r languages dist/
cp -r assets dist/
cp -r build dist/

# vendor/ was never copied, so the released zip contained no
# deactivation-feedback package at all and class_exists() was always false.
cp -r vendor dist/

# Strip dev-only metadata from vendored packages (avoids "hidden files" flags).
find dist/vendor \( -name '.gitignore' -o -name '.gitattributes' -o -name '.editorconfig' \
    -o -name 'phpcs.xml' -o -name 'phpunit.xml*' -o -name '.php-cs-fixer*' \) -delete 2>/dev/null || true
find dist/vendor -type d \( -name 'tests' -o -name 'test' -o -name 'docs' -o -name '.github' \
    -o -name 'examples' \) -exec rm -rf {} + 2>/dev/null || true

# Copy files
echo "Copying files..."
cp index.php dist/
cp readme.txt dist/
cp uninstall.php dist/
cp campaignbay.php dist/

# Create the zip file
echo "Creating zip file..."
cd dist
zip -r ../$ZIP_NAME .

# Clean up
cd ..
rm -rf dist

echo "Done! Created $ZIP_NAME containing:"
echo "- app/"
echo "- languages/"
echo "- assets/"
echo "- build/"
echo "- index.php"
echo "- readme.txt"
echo "- uninstall.php"
echo "- campaignbay.php"
