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

# Define dependency order - base packages with minimal dependencies first
# This order should match the order in split.yml workflow
declare -a DEPENDENCY_ORDER=(
    "packages/utils"
    "packages/http-client"
    "packages/templates"
    "packages/polyglot"
    "packages/setup"
    "packages/instructor"
    "packages/addons"
    "packages/auxiliary"
    "packages/evals"
    "packages/hub"
    "packages/tell"
    "packages/experimental"
)

# Define packages - must match those in sync-ver.sh
declare -A PACKAGES
PACKAGES["packages/utils"]="cognesy/instructor-utils"
PACKAGES["packages/http-client"]="cognesy/instructor-http-client"
PACKAGES["packages/templates"]="cognesy/instructor-templates"
PACKAGES["packages/polyglot"]="cognesy/instructor-polyglot"
PACKAGES["packages/setup"]="cognesy/instructor-setup"
PACKAGES["packages/instructor"]="cognesy/instructor-struct"
PACKAGES["packages/addons"]="cognesy/instructor-addons"
PACKAGES["packages/auxiliary"]="cognesy/instructor-auxiliary"
PACKAGES["packages/evals"]="cognesy/instructor-evals"
PACKAGES["packages/experimental"]="cognesy/instructor-experimental"
PACKAGES["packages/hub"]="cognesy/instructor-hub"
PACKAGES["packages/tell"]="cognesy/instructor-tell"

# 0. Verify all files exist
echo "Step 0: Verifying package directories..."
missing_dirs=()
for dir in "${!PACKAGES[@]}"; do
    if [ ! -d "$dir" ]; then
        missing_dirs+=("$dir")
    fi
done

if [ ${#missing_dirs[@]} -ne 0 ]; then
    echo "Error: The following package directories are missing:"
    for dir in "${missing_dirs[@]}"; do
        echo "  - $dir"
    done
    echo "Please check the package directory structure and try again."
    exit 1
fi

# 1. Build docs
echo "Step 1: Rebuilding documentation..."
./bin/instructor hub gendocs

# 2. Update all package versions using sync-ver.sh
echo "Step 2: Updating package versions in dependency order..."
./bin/sync-ver.sh "$VERSION"

# 3. Double-check all packages have correct version and dependencies
echo "Step 3: Verifying package versions and dependencies..."
for dir in "${!PACKAGES[@]}"; do
    if [ -d "$dir" ] && [ -f "$dir/composer.json" ]; then
        pkg_version=$(jq -r '.version // "0.0.0"' "$dir/composer.json")
        if [ "$pkg_version" != "$VERSION" ]; then
            echo "⚠️ Warning: Package $dir has incorrect version: $pkg_version (expected $VERSION)"
            echo "   Fixing version..."
            jq --arg version "$VERSION" '.version = $version' "$dir/composer.json" > "$dir/composer.json.tmp"
            mv "$dir/composer.json.tmp" "$dir/composer.json"
        fi

        # Check package dependencies
        MAJOR_MINOR=$(echo $VERSION | grep -o "^[0-9]*\.[0-9]*")
        for dep_dir in "${!PACKAGES[@]}"; do
            dep_pkg="${PACKAGES[$dep_dir]}"

            # Check in require section
            req_ver=$(jq -r --arg pkg "$dep_pkg" '.require[$pkg] // empty' "$dir/composer.json")
            if [ -n "$req_ver" ] && [ "$req_ver" != "^$MAJOR_MINOR" ]; then
                echo "⚠️ Warning: In $dir/composer.json, dependency $dep_pkg has version $req_ver instead of ^$MAJOR_MINOR"
                echo "   Fixing dependency version..."
                jq --arg pkg "$dep_pkg" --arg ver "^$MAJOR_MINOR" \
                   '.require[$pkg] = $ver' "$dir/composer.json" > "$dir/composer.json.tmp"
                mv "$dir/composer.json.tmp" "$dir/composer.json"
            fi

            # Check in require-dev section
            req_dev_ver=$(jq -r --arg pkg "$dep_pkg" '."require-dev"[$pkg] // empty' "$dir/composer.json")
            if [ -n "$req_dev_ver" ] && [ "$req_dev_ver" != "^$MAJOR_MINOR" ]; then
                echo "⚠️ Warning: In $dir/composer.json, dev dependency $dep_pkg has version $req_dev_ver instead of ^$MAJOR_MINOR"
                echo "   Fixing dev dependency version..."
                jq --arg pkg "$dep_pkg" --arg ver "^$MAJOR_MINOR" \
                   '."require-dev"[$pkg] = $ver' "$dir/composer.json" > "$dir/composer.json.tmp"
                mv "$dir/composer.json.tmp" "$dir/composer.json"
            fi
        done
    fi
done

# 4. Distribute release notes to all packages (in dependency order)
echo "Step 4: Distributing release notes to all packages..."
for dir in "${DEPENDENCY_ORDER[@]}"; do
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

# 5. Check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo "Step 5: Adding all modified files..."
    git add .
else
    echo "Step 5: No uncommitted changes detected."
fi

# 6. Check if there are changes to commit
if [ -n "$(git status --porcelain)" ]; then
    echo "Step 6: Committing changes..."
    git commit -m "Release version $VERSION"
    echo "✅ Changes committed."
else
    echo "Step 6: No changes to commit."
fi

# 7. Create git tag
echo "Step 7: Creating git tag..."
git tag -a "v$VERSION" -m "Release version $VERSION"
echo "✅ Created tag v$VERSION"

# 8. Push changes and tag
echo "Step 8: Pushing changes and tag..."
git push origin main && git push origin "v$VERSION"
echo "✅ Pushed changes and tag to origin"

# 9. Create GitHub release for main repo
echo "Step 9: Creating GitHub release..."
gh release create "v$VERSION" \
    --title "v$VERSION" \
    --notes-file "$NOTES_FILE" \
    --repo "$REPO"

echo "🎉 Release v$VERSION completed!"
echo "The split.yml workflow will now trigger automatically to split packages and create releases for each subpackage."
echo "Note: Package splitting will happen in the following dependency order to ensure proper package availability:"
for i in "${!DEPENDENCY_ORDER[@]}"; do
    dir="${DEPENDENCY_ORDER[$i]}"
    pkg="${PACKAGES[$dir]}"
    echo "  $((i+1)). $pkg ($dir)"
done
