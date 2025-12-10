#!/bin/bash

# PHP SDK Publish Script for Packagist
# This script prepares and publishes the PHP SDK to Packagist

set -e

VERSION=${1:-"3.0.0"}
REPO_NAME="complyance-php-sdk"
GITHUB_ORG="complyance-io"
PUBLIC_REPO_URL="https://github.com/${GITHUB_ORG}/${REPO_NAME}.git"

echo "🚀 Publishing PHP SDK v${VERSION} to Packagist"
echo "=============================================="

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: composer.json not found. Please run this script from sdk/complyance-php directory"
    exit 1
fi

# Check if git is initialized
if [ ! -d ".git" ]; then
    echo "📦 Initializing git repository..."
    git init
    git remote add origin ${PUBLIC_REPO_URL} 2>/dev/null || git remote set-url origin ${PUBLIC_REPO_URL}
fi

# Clean vendor directory (don't commit dependencies)
if [ -d "vendor" ]; then
    echo "🧹 Removing vendor directory (should not be committed)..."
    rm -rf vendor
fi

# Remove composer.lock (let Packagist generate it)
if [ -f "composer.lock" ]; then
    echo "🧹 Removing composer.lock..."
    rm -f composer.lock
fi

# Stage all files
echo "📝 Staging files..."
git add .

# Check if there are changes
if git diff --staged --quiet; then
    echo "ℹ️  No changes to commit"
else
    # Commit changes
    echo "💾 Committing changes..."
    git commit -m "Release v${VERSION}" || echo "No changes to commit"
fi

# Push to main branch
echo "📤 Pushing to main branch..."
git push origin main --force || git push -u origin main

# Create and push tag
echo "🏷️  Creating tag v${VERSION}..."
git tag -f "v${VERSION}" 2>/dev/null || git tag "v${VERSION}"
git push origin "v${VERSION}" --force || git push origin "v${VERSION}"

echo ""
echo "✅ Successfully published v${VERSION}!"
echo ""
echo "📋 Next steps:"
echo "1. Go to https://packagist.org/packages/submit"
echo "2. Submit repository URL: ${PUBLIC_REPO_URL}"
echo "3. Packagist will automatically detect version ${VERSION} from the tag"
echo ""
echo "🔄 For future releases, just run: ./publish.sh <version>"


