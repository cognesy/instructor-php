#!/bin/bash
# Remove shared resource files from all packages
set -e

echo "Removing shared resource files from all packages..."

# Find all package directories with composer.json files
for package_dir in packages/*/; do
    if [ -f "${package_dir}composer.json" ]; then
        package_name=$(basename "$package_dir")
        echo "Processing package: $package_name"
        
        # Remove config directory contents (but keep the directory)
        if [ -d "${package_dir}config" ]; then
            rm -rf "${package_dir}config/"*
            echo "  ✓ Removed config files"
        fi
        
        # Remove prompts directory
        if [ -d "${package_dir}prompts" ]; then
            rm -rf "${package_dir}prompts"
            echo "  ✓ Removed prompts directory"
        fi
        
        # Remove .env-dist file
        if [ -f "${package_dir}.env-dist" ]; then
            rm -f "${package_dir}.env-dist"
            echo "  ✓ Removed .env-dist file"
        fi
        
        # Remove bin directory contents (but keep the directory)
        if [ -d "${package_dir}bin" ]; then
            rm -rf "${package_dir}bin/"*
            echo "  ✓ Removed bin files"
        fi
        
        # Remove examples directory contents (but keep the directory)
        if [ -d "${package_dir}examples" ]; then
            rm -rf "${package_dir}examples/"*
            echo "  ✓ Removed examples"
        fi
        
        # Remove release notes contents (but keep the directory)
        if [ -d "${package_dir}release_notes" ]; then
            rm -rf "${package_dir}release_notes/"*
            echo "  ✓ Removed release notes"
        fi
    fi
done

# Clean up docs-build directory if it exists
if [ -d "docs-build" ]; then
    rm -rf docs-build/*
    echo "✓ Cleaned docs-build directory"
fi

echo "✅ Done! All shared resource files have been removed from packages."