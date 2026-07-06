#!/bin/bash

# Generate git diff for a package since last tagged release
# Usage: ./scripts/release-notes-diff.sh <package-name>
# Example: ./scripts/release-notes-diff.sh schema

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1" >&2
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1" >&2
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

# Check if package name is provided
if [ -z "$1" ]; then
    print_error "Package name required. Usage: $0 <package-name>"
    exit 1
fi

PACKAGE_NAME="$1"

# Check if we're in a git repository
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    print_error "Not inside a git repository"
    exit 1
fi

# Check if package exists
if [ ! -d "packages/$PACKAGE_NAME" ]; then
    print_error "Package 'packages/$PACKAGE_NAME' does not exist"
    exit 1
fi

# Find the latest git tag
latest_tag=$(git describe --tags --abbrev=0 2>/dev/null || echo "")

if [ -z "$latest_tag" ]; then
    print_warning "No tags found in repository. Showing all changes from initial commit..."
    # Get the first commit hash
    first_commit=$(git rev-list --max-parents=0 HEAD)
    from_ref="$first_commit"
else
    print_status "Latest tag: $latest_tag"
    from_ref="$latest_tag"
fi

# Get package-specific changes
print_status "Generating diff for package '$PACKAGE_NAME' since $from_ref"

# Output the diff
git diff "$from_ref"..HEAD -- "packages/$PACKAGE_NAME/"

# Check if there are any changes
if ! git diff --quiet "$from_ref"..HEAD -- "packages/$PACKAGE_NAME/"; then
    print_status "Changes found in package '$PACKAGE_NAME'" >&2
else
    print_warning "No changes found in package '$PACKAGE_NAME' since $from_ref" >&2
fi
