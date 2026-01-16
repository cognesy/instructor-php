#!/usr/bin/env bash
# load-packages.sh - Load package configuration from packages.json
# Compatible with bash 3.2+ (macOS default)

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "Error: jq is required but not installed. Please install jq first."
    exit 1
fi

# Get the script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="${1:-$(dirname "$SCRIPT_DIR")}"
PACKAGES_JSON="$PROJECT_ROOT/packages.json"

if [ ! -f "$PACKAGES_JSON" ]; then
    echo "Error: packages.json not found at $PACKAGES_JSON"
    exit 1
fi

# Function to get all package directories
get_package_dirs() {
    jq -r '.packages[].local' "$PACKAGES_JSON"
}

# Function to get composer name for a directory
get_composer_name() {
    local dir="$1"
    jq -r --arg dir "$dir" '.packages[] | select(.local == $dir) | .composer_name' "$PACKAGES_JSON"
}

# Function to get repo for a directory
get_repo() {
    local dir="$1"
    jq -r --arg dir "$dir" '.packages[] | select(.local == $dir) | .repo' "$PACKAGES_JSON"
}

# Function to get github name for a directory
get_github_name() {
    local dir="$1"
    jq -r --arg dir "$dir" '.packages[] | select(.local == $dir) | .github_name' "$PACKAGES_JSON"
}

# Function to get all composer names (for dependency updates)
get_all_composer_names() {
    jq -r '.packages[].composer_name' "$PACKAGES_JSON"
}

# Function to check if a composer name is an internal package
is_internal_package() {
    local pkg="$1"
    jq -e --arg pkg "$pkg" '.packages[] | select(.composer_name == $pkg)' "$PACKAGES_JSON" > /dev/null 2>&1
}
