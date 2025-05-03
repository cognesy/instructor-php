#!/usr/bin/env bash
# test-publish.sh - Test version of the publish script
# This version performs all operations except git commits, pushes, and releases

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

echo "=== TEST MODE === "
echo "This is a test run. No git commits, pushes, or releases will be made."
echo "Creating release for version $VERSION..."
echo "Using release notes from: $NOTES_FILE"

# Define packages - must match those in sync-ver.sh
declare -A PACKAGES
PACKAGES["packages/addons"]="cognesy/instructor-addons"
PACKAGES["packages/auxiliary"]="cognesy/instructor-auxiliary"
PACKAGES["packages/evals"]="cognesy/instructor-evals"
#PACKAGES["packages/experimental"]="cognesy/instructor-experimental"
PACKAGES["packages/http-client"]="cognesy/instructor-http-client"
PACKAGES["packages/hub"]="cognesy/instructor-hub"
PACKAGES["packages/instructor"]="cognesy/instructor-struct"
PACKAGES["packages/polyglot"]="cognesy/instructor-polyglot"
PACKAGES["packages/setup"]="cognesy/instructor-setup"
PACKAGES["packages/tell"]="cognesy/instructor-tell"
PACKAGES["packages/templates"]="cognesy/instructor-templates"
PACKAGES["packages/utils"]="cognesy/instructor-utils"

# Create a backup directory
BACKUP_DIR="test_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup important files
echo "Creating backups in $BACKUP_DIR..."
for dir in "${!PACKAGES[@]}"; do
    if [ -d "$dir" ] && [ -f "$dir/composer.json" ]; then
        mkdir -p "$BACKUP_DIR/$dir"
        cp "$dir/composer.json" "$BACKUP_DIR/$dir/"
        echo "✅ Backed up $dir/composer.json"
    fi
done

# Backup main composer.json
if [ -f "composer.json" ]; then
    cp "composer.json" "$BACKUP_DIR/"
    echo "✅ Backed up main composer.json"
fi

# 1. Test updating all package versions using sync-ver.sh
echo "Step 1: Testing package version updates..."
./bin/sync-ver.sh "$VERSION"

# 2. Test distributing release notes to all packages
echo "Step 2: Testing release notes distribution..."
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

# 3. Check what would be committed
echo "Step 3: Files that would be committed:"
git status --porcelain

# 4-7. Skip actual git operations in test mode
echo "Steps 4-7: Git operations would be performed here (skipped in test mode)"
echo "✓ Would commit changes with message: Release version $VERSION"
echo "✓ Would create tag: v$VERSION"
echo "✓ Would push to origin"
echo "✓ Would create GitHub release"

echo "🔄 Test completed! Restoring files from backup..."

# Restore from backup
for dir in "${!PACKAGES[@]}"; do
    if [ -d "$dir" ] && [ -f "$BACKUP_DIR/$dir/composer.json" ]; then
        cp "$BACKUP_DIR/$dir/composer.json" "$dir/"
        echo "✅ Restored $dir/composer.json"
    fi
done

# Restore main composer.json
if [ -f "$BACKUP_DIR/composer.json" ]; then
    cp "$BACKUP_DIR/composer.json" "composer.json"
    echo "✅ Restored main composer.json"
fi

# Clean up release notes copies
for dir in "${!PACKAGES[@]}"; do
    if [ -d "$dir/release_notes" ]; then
        rm -f "$dir/release_notes/v$VERSION.md"
        echo "✅ Cleaned up release notes in $dir/release_notes/"
    fi
done

echo "🎉 Test complete! All original files have been restored."
echo "The release process simulation was successful."