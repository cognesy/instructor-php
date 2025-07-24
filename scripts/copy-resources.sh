#!/bin/bash
# Copy shared resource files to packages automatically
set -e

SOURCE_DIR="."
echo "Copying shared resource files to packages..."

# Define which binary goes to which package
declare -A BINARY_MAP
BINARY_MAP["instructor-setup"]="setup"
BINARY_MAP["instructor-hub"]="hub"  
BINARY_MAP["tell"]="tell"

# Define packages that need specific resource types
# These can be determined by checking composer.json bin entries or package purpose
PACKAGES_NEEDING_CONFIG=("config" "templates" "setup" "http-client" "polyglot" "instructor" "tell" "hub")
PACKAGES_NEEDING_ENV=("config" "setup" "polyglot" "instructor" "tell" "hub")
PACKAGES_NEEDING_PROMPTS=("templates" "setup" "polyglot" "instructor" "tell" "hub")
PACKAGES_NEEDING_EXAMPLES=("hub")

# Helper function to check if package is in array
package_needs_resource() {
    local package="$1"
    local -n resource_array=$2
    
    for needed_package in "${resource_array[@]}"; do
        if [[ "$package" == "$needed_package" ]]; then
            return 0
        fi
    done
    return 1
}

# Process all packages with composer.json files
for package_dir in packages/*/; do
    if [[ -f "${package_dir}composer.json" ]]; then
        package_name=$(basename "$package_dir")
        echo "Processing package: $package_name"
        
        # Copy config files if package needs them
        if package_needs_resource "$package_name" PACKAGES_NEEDING_CONFIG; then
            if [[ -d "$SOURCE_DIR/config" ]]; then
                rm -rf "${package_dir}config"
                mkdir -p "${package_dir}config"
                cp -R "$SOURCE_DIR/config/"* "${package_dir}config/"
                echo "  ✓ Copied config files"
            fi
        fi
        
        # Copy .env-dist if package needs it
        if package_needs_resource "$package_name" PACKAGES_NEEDING_ENV; then
            if [[ -f "$SOURCE_DIR/.env-dist" ]]; then
                cp "$SOURCE_DIR/.env-dist" "${package_dir}.env-dist"
                echo "  ✓ Copied .env-dist file"
            fi
        fi
        
        # Copy prompts if package needs them
        if package_needs_resource "$package_name" PACKAGES_NEEDING_PROMPTS; then
            if [[ -d "$SOURCE_DIR/prompts" ]]; then
                rm -rf "${package_dir}prompts"
                mkdir -p "${package_dir}prompts"
                cp -R "$SOURCE_DIR/prompts/"* "${package_dir}prompts/"
                echo "  ✓ Copied prompts"
            fi
        fi
        
        # Copy examples if package needs them
        if package_needs_resource "$package_name" PACKAGES_NEEDING_EXAMPLES; then
            if [[ -d "$SOURCE_DIR/examples" ]]; then
                rm -rf "${package_dir}examples"
                mkdir -p "${package_dir}examples"
                cp -R "$SOURCE_DIR/examples/"* "${package_dir}examples/"
                echo "  ✓ Copied examples"
            fi
        fi
        
        # Copy specific binary files based on mapping
        for binary_name in "${!BINARY_MAP[@]}"; do
            target_package="${BINARY_MAP[$binary_name]}"
            if [[ "$package_name" == "$target_package" ]]; then
                if [[ -f "$SOURCE_DIR/bin/$binary_name" ]]; then
                    rm -rf "${package_dir}bin"
                    mkdir -p "${package_dir}bin"
                    cp "$SOURCE_DIR/bin/$binary_name" "${package_dir}bin/"
                    echo "  ✓ Copied binary: $binary_name"
                fi
            fi
        done
    fi
done

echo "✅ Done! All shared resources have been copied to appropriate packages."