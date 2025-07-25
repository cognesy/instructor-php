#!/usr/bin/env bash
# load-packages.sh - Load package configuration from packages.json

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

# Function to load packages into associative arrays
load_packages() {
    # Clear any existing arrays
    unset PACKAGES
    unset PACKAGE_REPOS
    unset PACKAGE_GITHUB_NAMES
    
    declare -gA PACKAGES
    declare -gA PACKAGE_REPOS
    declare -gA PACKAGE_GITHUB_NAMES
    
    # Read packages from JSON and populate arrays
    while IFS=$'\t' read -r local repo github_name composer_name; do
        PACKAGES["$local"]="$composer_name"
        PACKAGE_REPOS["$local"]="$repo"
        PACKAGE_GITHUB_NAMES["$local"]="$github_name"
    done < <(jq -r '.packages[] | [.local, .repo, .github_name, .composer_name] | @tsv' "$PACKAGES_JSON")
}

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

# Load packages when script is sourced
load_packages