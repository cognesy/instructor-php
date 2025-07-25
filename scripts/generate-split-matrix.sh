#!/usr/bin/env bash
# generate-split-matrix.sh - Generate GitHub Actions matrix from packages.json

set -e

# Load centralized package configuration  
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="${1:-$(dirname "$SCRIPT_DIR")}"
source "$SCRIPT_DIR/load-packages.sh" "$PROJECT_ROOT"

# Generate YAML matrix entries
echo "          # Generated from packages.json - DO NOT EDIT MANUALLY"

while IFS=$'\t' read -r local repo github_name composer_name; do
    echo "          - local: '$local'"
    echo "            repo:  '$repo'"
    echo "            name:  '$github_name'"
done < <(jq -r '.packages[] | [.local, .repo, .github_name, .composer_name] | @tsv' "$PACKAGES_JSON")