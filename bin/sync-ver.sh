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

# Define packages in dependency order - base packages first
# This order is critical - must match the order in split.yml
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
)

# Map package directories to package names
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

# Define which packages go in which section of the main composer.json
declare -A MAIN_REQUIRE_PACKAGES
MAIN_REQUIRE_PACKAGES["packages/addons"]="cognesy/instructor-addons"
MAIN_REQUIRE_PACKAGES["packages/http-client"]="cognesy/instructor-http-client"
MAIN_REQUIRE_PACKAGES["packages/instructor"]="cognesy/instructor-struct"
MAIN_REQUIRE_PACKAGES["packages/polyglot"]="cognesy/instructor-polyglot"
MAIN_REQUIRE_PACKAGES["packages/setup"]="cognesy/instructor-setup"
MAIN_REQUIRE_PACKAGES["packages/templates"]="cognesy/instructor-templates"
MAIN_REQUIRE_PACKAGES["packages/utils"]="cognesy/instructor-utils"

declare -A MAIN_REQUIRE_DEV_PACKAGES
MAIN_REQUIRE_DEV_PACKAGES["packages/auxiliary"]="cognesy/instructor-auxiliary"
MAIN_REQUIRE_DEV_PACKAGES["packages/evals"]="cognesy/instructor-evals"
MAIN_REQUIRE_DEV_PACKAGES["packages/experimental"]="cognesy/instructor-experimental"
MAIN_REQUIRE_DEV_PACKAGES["packages/hub"]="cognesy/instructor-hub"
MAIN_REQUIRE_DEV_PACKAGES["packages/tell"]="cognesy/instructor-tell"

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

    # Check if package version is already up to date
    local current_version=$(jq -r '.version // "0.0.0"' "$package_dir/composer.json")
    if [ "$current_version" = "$VERSION" ]; then
        echo "✅ Package $package_name is already at version $VERSION"
        return
    fi

    # Update version field
    jq --arg version "$VERSION" '.version = $version' "$package_dir/composer.json" > "$package_dir/composer.json.tmp"

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

# Function to update package versions in main composer.json sections
update_main_composer() {
    echo "Updating main composer.json..."

    if [ ! -f "composer.json" ]; then
        echo "⚠️ Warning: Main composer.json does not exist, skipping..."
        return
    fi

    # Create a temporary file
    cp composer.json composer.json.tmp

    # Update require section
    for dir in "${!MAIN_REQUIRE_PACKAGES[@]}"; do
        pkg_name="${MAIN_REQUIRE_PACKAGES[$dir]}"
        echo "  - Updating $pkg_name in require section to ^$MAJOR_MINOR"
        jq --arg pkg "$pkg_name" --arg ver "^$MAJOR_MINOR" \
           '.require[$pkg] = $ver' composer.json.tmp > composer.json.tmp2
        mv composer.json.tmp2 composer.json.tmp
    done

    # Update require-dev section
    for dir in "${!MAIN_REQUIRE_DEV_PACKAGES[@]}"; do
        pkg_name="${MAIN_REQUIRE_DEV_PACKAGES[$dir]}"
        echo "  - Updating $pkg_name in require-dev section to ^$MAJOR_MINOR"
        jq --arg pkg "$pkg_name" --arg ver "^$MAJOR_MINOR" \
           '."require-dev"[$pkg] = $ver' composer.json.tmp > composer.json.tmp2
        mv composer.json.tmp2 composer.json.tmp
    done

    # Apply changes
    mv composer.json.tmp composer.json
    echo "✅ Updated main composer.json"
}

# First, list the packages that will be processed
echo "The following packages will be updated to version $VERSION (in dependency order):"
for dir in "${DEPENDENCY_ORDER[@]}"; do
    pkg_name="${PACKAGES[$dir]}"
    echo "  - $pkg_name ($dir)"
done

# Update individual package versions in dependency order
echo -e "\nUpdating package versions..."
for dir in "${DEPENDENCY_ORDER[@]}"; do
    pkg_name="${PACKAGES[$dir]}"
    update_package_version "$dir" "$pkg_name"
done

# Update main composer.json
update_main_composer

echo -e "\n🎉 Version sync complete!"
echo "All packages and dependencies updated to version $VERSION"

# Validate dependency consistency
echo -e "\nValidating dependency consistency..."
for dir in "${DEPENDENCY_ORDER[@]}"; do
    if [ ! -f "$dir/composer.json" ]; then
        continue
    fi

    # Check if all internal dependencies are set to the correct version
    for dep_dir in "${!PACKAGES[@]}"; do
        dep_pkg="${PACKAGES[$dep_dir]}"

        # Check in require section
        req_ver=$(jq -r --arg pkg "$dep_pkg" '.require[$pkg] // empty' "$dir/composer.json")
        if [ -n "$req_ver" ] && [ "$req_ver" != "^$MAJOR_MINOR" ]; then
            echo "⚠️ Warning: In $dir/composer.json, dependency $dep_pkg has version $req_ver instead of ^$MAJOR_MINOR"
            # Automatically fix this
            jq --arg pkg "$dep_pkg" --arg ver "^$MAJOR_MINOR" \
               '.require[$pkg] = $ver' "$dir/composer.json" > "$dir/composer.json.tmp"
            mv "$dir/composer.json.tmp" "$dir/composer.json"
            echo "  ✅ Fixed: Updated to ^$MAJOR_MINOR"
        fi

        # Check in require-dev section
        req_dev_ver=$(jq -r --arg pkg "$dep_pkg" '."require-dev"[$pkg] // empty' "$dir/composer.json")
        if [ -n "$req_dev_ver" ] && [ "$req_dev_ver" != "^$MAJOR_MINOR" ]; then
            echo "⚠️ Warning: In $dir/composer.json, dev dependency $dep_pkg has version $req_dev_ver instead of ^$MAJOR_MINOR"
            # Automatically fix this
            jq --arg pkg "$dep_pkg" --arg ver "^$MAJOR_MINOR" \
               '."require-dev"[$pkg] = $ver' "$dir/composer.json" > "$dir/composer.json.tmp"
            mv "$dir/composer.json.tmp" "$dir/composer.json"
            echo "  ✅ Fixed: Updated to ^$MAJOR_MINOR"
        fi
    done
done

echo "✅ Dependency validation complete!"
