#!/bin/bash
# Copy shared resource files to packages automatically
# Compatible with bash 3.2+ (macOS default)
set -e

SOURCE_DIR="."
echo "Copying shared resource files to packages..."

# Helper function to check if package needs config files
needs_config() {
    case "$1" in
        config|templates|setup|http-client|polyglot|instructor|tell|hub) return 0 ;;
        *) return 1 ;;
    esac
}

# Helper function to check if package needs .env-dist
needs_env() {
    case "$1" in
        config|setup|polyglot|instructor|tell|hub) return 0 ;;
        *) return 1 ;;
    esac
}

# Helper function to check if package needs prompts
needs_prompts() {
    case "$1" in
        templates|setup|polyglot|instructor|tell|hub) return 0 ;;
        *) return 1 ;;
    esac
}

# Helper function to check if package needs examples
needs_examples() {
    case "$1" in
        hub) return 0 ;;
        *) return 1 ;;
    esac
}

# Helper function to get binary name for a package
get_binary_for_package() {
    case "$1" in
        setup) echo "instructor-setup" ;;
        hub) echo "instructor-hub" ;;
        tell) echo "tell" ;;
        *) echo "" ;;
    esac
}

# Process all packages with composer.json files
for package_dir in packages/*/; do
    if [[ -f "${package_dir}composer.json" ]]; then
        package_name=$(basename "$package_dir")
        echo "Processing package: $package_name"

        # Copy config files if package needs them
        if needs_config "$package_name"; then
            if [[ -d "$SOURCE_DIR/config" ]]; then
                rm -rf "${package_dir}config"
                mkdir -p "${package_dir}config"
                cp -R "$SOURCE_DIR/config/"* "${package_dir}config/"
                echo "  ✓ Copied config files"
            fi
        fi

        # Copy .env-dist if package needs it
        if needs_env "$package_name"; then
            if [[ -f "$SOURCE_DIR/.env-dist" ]]; then
                cp "$SOURCE_DIR/.env-dist" "${package_dir}.env-dist"
                echo "  ✓ Copied .env-dist file"
            fi
        fi

        # Copy prompts if package needs them
        if needs_prompts "$package_name"; then
            if [[ -d "$SOURCE_DIR/prompts" ]]; then
                rm -rf "${package_dir}prompts"
                mkdir -p "${package_dir}prompts"
                cp -R "$SOURCE_DIR/prompts/"* "${package_dir}prompts/"
                echo "  ✓ Copied prompts"
            fi
        fi

        # Copy examples if package needs them
        if needs_examples "$package_name"; then
            if [[ -d "$SOURCE_DIR/examples" ]]; then
                rm -rf "${package_dir}examples"
                mkdir -p "${package_dir}examples"
                cp -R "$SOURCE_DIR/examples/"* "${package_dir}examples/"
                echo "  ✓ Copied examples"
            fi
        fi

        # Copy specific binary files based on package
        binary_name=$(get_binary_for_package "$package_name")
        if [[ -n "$binary_name" && -f "$SOURCE_DIR/bin/$binary_name" ]]; then
            rm -rf "${package_dir}bin"
            mkdir -p "${package_dir}bin"
            cp "$SOURCE_DIR/bin/$binary_name" "${package_dir}bin/"
            echo "  ✓ Copied binary: $binary_name"
        fi
    fi
done

echo "✅ Done! All shared resources have been copied to appropriate packages."
