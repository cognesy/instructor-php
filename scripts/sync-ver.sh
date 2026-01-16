#!/usr/bin/env bash
# sync-ver.sh - Synchronizes versions across explicitly defined packages
# Compatible with bash 3.2+ (macOS default)
set -e  # Exit immediately if a command exits with non-zero status

# Get the new version from tag
VERSION=$1
if [ -z "$VERSION" ]; then
    echo "Please provide version number"
    exit 1
fi

# Remove 'v' prefix if present
VERSION=${VERSION#v}

# Extract major.minor version for dependency constraints
MAJOR_MINOR=$(echo $VERSION | grep -o "^[0-9]*\.[0-9]*")

echo "Updating to version $VERSION (dependency constraint ^$MAJOR_MINOR)"

# Load centralized package configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
source "$SCRIPT_DIR/load-packages.sh" "$PROJECT_ROOT"

# Update version in each package's composer.json
update_package_version() {
    local package_dir=$1
    local package_name=$2

    if [ ! -d "$package_dir" ]; then
        echo "⚠️ Warning: Directory $package_dir does not exist, skipping..."
        return
    fi

    if [ ! -f "$package_dir/composer.json" ]; then
        echo "⚠️ Warning: $package_dir/composer.json does not exist, skipping..."
        return
    fi

    echo "Updating $package_name in $package_dir..."

    # Update version field
    cp "$package_dir/composer.json" "$package_dir/composer.json.tmp"

    # Update internal dependencies to use ^MAJOR.MINOR
    package_tmp=$(cat "$package_dir/composer.json.tmp")

    # Process require section if it exists
    if jq -e '.require' "$package_dir/composer.json.tmp" > /dev/null 2>&1; then
        while IFS= read -r pkg; do
            package_tmp=$(echo "$package_tmp" | jq --arg pkg "$pkg" --arg ver "^$MAJOR_MINOR" \
                'if .require[$pkg] then .require[$pkg] = $ver else . end')
        done < <(get_all_composer_names)
    fi

    # Process require-dev section if it exists
    if jq -e '."require-dev"' "$package_dir/composer.json.tmp" > /dev/null 2>&1; then
        while IFS= read -r pkg; do
            package_tmp=$(echo "$package_tmp" | jq --arg pkg "$pkg" --arg ver "^$MAJOR_MINOR" \
                'if ."require-dev"[$pkg] then ."require-dev"[$pkg] = $ver else . end')
        done < <(get_all_composer_names)
    fi

    echo "$package_tmp" > "$package_dir/composer.json"
    rm -f "$package_dir/composer.json.tmp"

    echo "✅ Updated $package_name to version $VERSION with appropriate dependency constraints"
}

# List the packages that will be processed
echo "The following packages will be updated to version $VERSION:"
while IFS= read -r dir; do
    pkg_name=$(get_composer_name "$dir")
    echo "  - $pkg_name ($dir)"
done < <(get_package_dirs)

# Update individual package versions
echo -e "\nUpdating package versions..."
while IFS= read -r dir; do
    pkg_name=$(get_composer_name "$dir")
    update_package_version "$dir" "$pkg_name"
done < <(get_package_dirs)

echo -e "\n🎉 Version sync complete!"
echo "All packages and dependencies updated to version $VERSION"
