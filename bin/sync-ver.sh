#!/bin/bash

# Get the new version from tag
VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Please provide version number"
    exit 1
fi

# Remove 'v' prefix if present
VERSION=${VERSION#v}

# Define packages and their sections based on composer.json
declare -A REQUIRE_PACKAGES=(
    ["src-utils"]="cognesy/instructor-utils"
    ["src-addons"]="cognesy/instructor-addons"
    ["src-polyglot"]="cognesy/instructor-polyglot"
    ["src-instructor"]="cognesy/instructor-core"
)

declare -A REQUIRE_DEV_PACKAGES=(
    ["src-aux"]="cognesy/instructor-auxiliary"
    ["src-experimental"]="cognesy/instructor-experimental"
    ["src-hub"]="cognesy/instructor-hub"
    ["src-setup"]="cognesy/instructor-setup"
    ["src-tell"]="cognesy/tell-cli"
)

# Update version in each package's composer.json
update_package_version() {
    local package_dir=$1
    if [ -f "$package_dir/composer.json" ]; then
        # Update version while preserving the rest of the file
        jq --arg version "$VERSION" '. + {version: $version}' "$package_dir/composer.json" > "$package_dir/composer.json.tmp"
        mv "$package_dir/composer.json.tmp" "$package_dir/composer.json"
        echo "Updated $package_dir to version $VERSION"
    fi
}

# Function to update package versions in composer.json sections
update_main_composer() {
    local section=$1
    local package_name=$2
    local new_content

    echo "Attempting to update $package_name in $section..."

    # Create a temporary file with updated content
    new_content=$(jq --arg section "$section" \
                    --arg pkg "$package_name" \
                    --arg ver "^$VERSION" \
                    'setpath([$section, $pkg]; $ver)' \
                    composer.json)

    # Check if the content actually changed
    if [ "$new_content" != "$(cat composer.json)" ]; then
        echo "$new_content" > composer.json
        echo "Updated $package_name to version $VERSION in $section"
    else
        echo "Package $package_name not found in $section"
    fi
}

# First, let's check what packages are actually in composer.json
echo "Checking current package versions in composer.json..."
echo "In require:"
jq -r '.require | keys[]' composer.json | grep '^cognesy/'
echo "In require-dev:"
jq -r '."require-dev" | keys[]' composer.json | grep '^cognesy/'

# Update package versions
echo "Updating package versions..."
for dir in "${!REQUIRE_PACKAGES[@]}"; do
    update_package_version "$dir"
done

for dir in "${!REQUIRE_DEV_PACKAGES[@]}"; do
    update_package_version "$dir"
done

# Update main composer.json
echo "Updating main composer.json..."

# Update require section
for dir in "${!REQUIRE_PACKAGES[@]}"; do
    pkg_name="${REQUIRE_PACKAGES[$dir]}"
    update_main_composer "require" "$pkg_name"
done

# Update require-dev section
for dir in "${!REQUIRE_DEV_PACKAGES[@]}"; do
    pkg_name="${REQUIRE_DEV_PACKAGES[$dir]}"
    update_main_composer "require-dev" "$pkg_name"
done

echo "Version sync complete"