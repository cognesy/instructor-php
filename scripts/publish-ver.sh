#!/usr/bin/env bash
# publish.sh - Main script for releasing a new version
set -e  # Exit immediately if a command exits with non-zero status

VERSION=$1
REPO="cognesy/instructor-php"

if [ -z "$VERSION" ]; then
    echo "Please provide version number"
    exit 1
fi

# Remove 'v' prefix if present
VERSION=${VERSION#v}

# Check if release notes exist
NOTES_FILE="docs/release-notes/v$VERSION.mdx"
if [ ! -f "$NOTES_FILE" ]; then
    echo "Error: Release notes file not found at $NOTES_FILE"
    echo "Please create release notes file before proceeding"
    exit 1
fi

echo "Creating release for version $VERSION..."
echo "Using release notes from: $NOTES_FILE"

# Load centralized package configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
source "$SCRIPT_DIR/load-packages.sh" "$PROJECT_ROOT"

# 0. Build docs
echo "Step 0: Rebuilding documentation..."
composer docs gen:mintlify
composer docs gen:mkdocs

# 0.1. Copy resource files
echo "Step 0.1: Copying resource files..."
./scripts/copy-resources.sh

# 1. Update all package versions using sync-ver.sh
echo "Step 1: Updating package versions..."
./scripts/sync-ver.sh "$VERSION"

# 2. Distribute release notes to all packages
echo "Step 2: Distributing release notes to all packages..."
for dir in "${!PACKAGES[@]}"; do
    if [ -d "$dir" ]; then
        # Create release_notes directory if it doesn't exist
        mkdir -p "$dir/release_notes"

        # Copy the release notes file to the package (convert from .mdx to .md)
        cp "$NOTES_FILE" "$dir/release_notes/v$VERSION.md"

        echo "✅ Copied release notes to $dir/release_notes/"
    else
        echo "⚠️ Warning: Directory $dir does not exist, skipping..."
    fi
done

# 3. Check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo "Step 3: Adding all modified files..."
    git add .
else
    echo "Step 3: No uncommitted changes detected."
fi

# 4. Check if there are changes to commit
if [ -n "$(git status --porcelain)" ]; then
    echo "Step 4: Committing changes..."
    git commit -m "Release version $VERSION"
    echo "✅ Changes committed."
else
    echo "Step 4: No changes to commit."
fi

# 5. Create git tag
echo "Step 5: Creating git tag..."
git tag -a "v$VERSION" -m "Release version $VERSION"
echo "✅ Created tag v$VERSION"

# 6. Push changes and tag
echo "Step 6: Pushing changes and tag..."
git push origin main && git push origin "v$VERSION"
echo "✅ Pushed changes and tag to origin"

# 7. Create GitHub release for main repo
echo "Step 7: Creating GitHub release..."
gh release create "v$VERSION" \
    --title "v$VERSION" \
    --notes-file "$NOTES_FILE" \
    --repo "$REPO"

echo "🎉 Release v$VERSION completed!"
echo "The split.yml workflow will now trigger automatically to split packages and create releases for each subpackage."