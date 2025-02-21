#!/bin/bash

VERSION=$1
REPO="cognesy/instructor-php"

if [ -z "$VERSION" ]; then
    echo "Please provide version number"
    exit 1
fi

# Remove 'v' prefix if present
VERSION=${VERSION#v}

# Check if release notes exist
NOTES_FILE="docs/release-notes/v$VERSION.md"
if [ ! -f "$NOTES_FILE" ]; then
    echo "Error: Release notes file not found at $NOTES_FILE"
    echo "Please create release notes file before proceeding"
    exit 1
fi

echo "Creating release for version $VERSION..."
echo "Using release notes from: $NOTES_FILE"

# 1. Update package versions
# ./bin/sync-ver.sh "$VERSION"

# 2. Commit changes
git commit -am "Release version $VERSION"

# 3. Create git tag
git tag "v$VERSION"

# 4. Push changes and tag
git push && git push --tags

# 5. Create GitHub release using notes from file
gh release create "v$VERSION" \
    --title "$VERSION" \
    --notes-file "$NOTES_FILE" \
    --repo "$REPO"

echo "Release v$VERSION completed!"