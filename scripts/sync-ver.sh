#!/usr/bin/env bash
# sync-ver.sh - Synchronizes versions across explicitly defined packages
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

# Define packages and their sections based on composer.json
declare -A PACKAGES
PACKAGES["packages/addons"]="cognesy/instructor-addons"
PACKAGES["packages/auxiliary"]="cognesy/instructor-auxiliary"
PACKAGES["packages/config"]="cognesy/instructor-config"
#PACKAGES["packages/experimental"]="cognesy/instructor-experimental"
PACKAGES["packages/evals"]="cognesy/instructor-evals"
PACKAGES["packages/events"]="cognesy/instructor-events"
PACKAGES["packages/http-client"]="cognesy/instructor-http-client"
PACKAGES["packages/hub"]="cognesy/instructor-hub"
PACKAGES["packages/instructor"]="cognesy/instructor-struct"
PACKAGES["packages/polyglot"]="cognesy/instructor-polyglot"
PACKAGES["packages/schema"]="cognesy/instructor-schema"
PACKAGES["packages/schema-v6"]="cognesy/instructor-schema-v6"
PACKAGES["packages/setup"]="cognesy/instructor-setup"
PACKAGES["packages/tell"]="cognesy/instructor-tell"
PACKAGES["packages/templates"]="cognesy/instructor-templates"
PACKAGES["packages/utils"]="cognesy/instructor-utils"

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "Error: jq is required but not installed. Please install jq first."
    exit 1
fi

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
    #jq --arg version "$VERSION" '.version = $version' "$package_dir/composer.json" > "$package_dir/composer.json.tmp"
    cp "$package_dir/composer.json" "$package_dir/composer.json.tmp"

    # Update internal dependencies to use ^MAJOR.MINOR
    package_tmp=$(cat "$package_dir/composer.json.tmp")

    # Process require section if it exists
    if jq -e '.require' "$package_dir/composer.json.tmp" > /dev/null 2>&1; then
        for pkg in "${PACKAGES[@]}"; do
            package_tmp=$(echo "$package_tmp" | jq --arg pkg "$pkg" --arg ver "^$MAJOR_MINOR" \
                'if .require[$pkg] then .require[$pkg] = $ver else . end')
        done
    fi

    # Process require-dev section if it exists
    if jq -e '."require-dev"' "$package_dir/composer.json.tmp" > /dev/null 2>&1; then
        for pkg in "${PACKAGES[@]}"; do
            package_tmp=$(echo "$package_tmp" | jq --arg pkg "$pkg" --arg ver "^$MAJOR_MINOR" \
                'if ."require-dev"[$pkg] then ."require-dev"[$pkg] = $ver else . end')
        done
    fi

    echo "$package_tmp" > "$package_dir/composer.json"
    rm -f "$package_dir/composer.json.tmp"

    echo "✅ Updated $package_name to version $VERSION with appropriate dependency constraints"
}

# List the packages that will be processed
echo "The following packages will be updated to version $VERSION:"
for dir in "${!PACKAGES[@]}"; do
    pkg_name="${PACKAGES[$dir]}"
    echo "  - $pkg_name ($dir)"
done

# Update individual package versions
echo -e "\nUpdating package versions..."
for dir in "${!PACKAGES[@]}"; do
    pkg_name="${PACKAGES[$dir]}"
    update_package_version "$dir" "$pkg_name"
done

echo -e "\n🎉 Version sync complete!"
echo "All packages and dependencies updated to version $VERSION"