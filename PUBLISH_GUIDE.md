# PHP SDK Publishing Guide

This guide explains how to publish the PHP SDK to Packagist, similar to how we publish .NET and TypeScript SDKs.

## Prerequisites

1. **Create a public GitHub repository** (one-time setup):
   - Go to: https://github.com/organizations/complyance-io/repositories/new
   - Repository name: `complyance-php-sdk`
   - Description: "Official PHP SDK for Complyance Unified e-invoicing platform"
   - Visibility: **Public** (required for Packagist)
   - Don't initialize with README/license (we'll copy from monorepo)

## Initial Setup (One-Time)

### Step 1: Clone and prepare the public repository

```bash
# Navigate to PHP SDK directory
cd sdk/complyance-php

# Clone the new empty public repository
git clone https://github.com/complyance-io/complyance-php-sdk.git ../complyance-php-sdk-public
cd ../complyance-php-sdk-public

# Copy all files from monorepo (excluding vendor)
cp -r ../complyance-php/* .
rm -rf vendor composer.lock .git

# Initialize git
git init
git remote add origin https://github.com/complyance-io/complyance-php-sdk.git

# Commit and push
git add .
git commit -m "Initial release: PHP SDK v3.0.0"
git branch -M main
git push -u origin main

# Create and push version tag
git tag v3.0.0
git push origin v3.0.0
```

### Step 2: Submit to Packagist (one-time)

1. Go to: https://packagist.org/packages/submit
2. Enter repository URL: `https://github.com/complyance-io/complyance-php-sdk`
3. Click "Submit"
4. Packagist will automatically detect version `3.0.0` from the tag

## Publishing New Versions

After the initial setup, publishing new versions is simple:

### Option 1: Using the publish script (Recommended)

```bash
cd sdk/complyance-php

# Update version in composer.json first, then:
./publish.sh 3.0.1
```

### Option 2: Manual commands

```bash
cd sdk/complyance-php

# 1. Update version in composer.json
# 2. Copy changes to public repo (or work directly in public repo)
cd ../complyance-php-sdk-public

# 3. Commit changes
git add .
git commit -m "Release v3.0.1"
git push origin main

# 4. Create and push tag
git tag v3.0.1
git push origin v3.0.1

# 5. Update Packagist (optional - it auto-updates, but you can trigger manually)
# Go to: https://packagist.org/packages/io.complyance/unify-sdk
# Click "Update" button
```

## Automated Workflow (Future)

You can set up a GitHub Action to automatically publish on tag creation:

```yaml
# .github/workflows/publish-php-sdk.yml
name: Publish PHP SDK

on:
  push:
    tags:
      - 'v*'

jobs:
  publish:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Trigger Packagist update
        run: |
          curl -X POST https://packagist.org/api/update-package?username=YOUR_USERNAME&apiToken=YOUR_TOKEN \
            -d '{"repository":{"url":"https://github.com/complyance-io/complyance-php-sdk"}}'
```

## Package Details

- **Package Name:** `io.complyance/unify-sdk`
- **Current Version:** `3.0.0`
- **Repository:** https://github.com/complyance-io/complyance-php-sdk
- **Packagist URL:** https://packagist.org/packages/io.complyance/unify-sdk

## Installation

Users can install the package via:

```bash
composer require io.complyance/unify-sdk
```

## Notes

- Packagist automatically updates when you push new git tags
- Version tags must follow semantic versioning (e.g., `v3.0.0`, `v3.0.1`)
- The `composer.json` version field should match the git tag
- Never commit `vendor/` or `composer.lock` to the public repository


