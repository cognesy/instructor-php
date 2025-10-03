#!/bin/bash

# Generate combined release notes for all changed packages using Claude CLI
# Usage: ./scripts/release-notes-all.sh

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

# Check if we're in a git repository
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    print_error "Not inside a git repository"
    exit 1
fi

# Check if claude CLI is available
if ! command -v claude >/dev/null 2>&1; then
    print_error "Claude CLI not found. Please install claude CLI first."
    exit 1
fi

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Find the latest git tag
latest_tag=$(git describe --tags --abbrev=0 2>/dev/null || echo "")

if [ -z "$latest_tag" ]; then
    print_warning "No tags found in repository. Analyzing all changes from initial commit..."
    first_commit=$(git rev-list --max-parents=0 HEAD)
    from_ref="$first_commit"
    version_label="Initial Release"
else
    print_status "Latest tag: $latest_tag"
    from_ref="$latest_tag"
    version_label="since $latest_tag"
fi

# Get all packages with changes since last tag
get_changed_packages() {
    git diff --name-only "$from_ref"..HEAD | \
        grep "^packages/[^/]*/src/" | \
        sed 's|^packages/\([^/]*\)/.*|\1|' | \
        sort -u
}

changed_packages=$(get_changed_packages)

if [ -z "$changed_packages" ]; then
    print_warning "No package changes found $version_label"
    exit 1
fi

num_packages=$(echo "$changed_packages" | wc -l)
print_status "Found $num_packages package(s) with changes $version_label"

# Create temporary file for collecting all package summaries
temp_file=$(mktemp)

echo "=== RELEASE NOTES SUMMARY ===" > "$temp_file"
echo "Version: $version_label" >> "$temp_file"
echo "Total packages changed: $num_packages" >> "$temp_file"
echo "" >> "$temp_file"

# Generate individual package summaries
echo "=== PACKAGE SUMMARIES ===" >> "$temp_file"
echo "" >> "$temp_file"

for package in $changed_packages; do
    print_status "Generating summary for package: $package"

    # Get package statistics
    stats=$(git diff --numstat "$from_ref"..HEAD -- "packages/$package/" | \
        awk '{added+=$1; removed+=$2; files++} END {print added+removed, added, removed, files}')

    total_lines=$(echo "$stats" | awk '{print $1}')
    added_lines=$(echo "$stats" | awk '{print $2}')
    removed_lines=$(echo "$stats" | awk '{print $3}')
    changed_files=$(echo "$stats" | awk '{print $4}')

    echo "--- Package: $package ---" >> "$temp_file"
    echo "Lines changed: $total_lines (+$added_lines/-$removed_lines)" >> "$temp_file"
    echo "Files changed: $changed_files" >> "$temp_file"
    echo "" >> "$temp_file"

    # Generate individual package release notes
    package_notes=$("$SCRIPT_DIR/release-notes-summary.sh" "$package" 2>/dev/null || echo "Failed to generate notes for $package")

    echo "$package_notes" >> "$temp_file"
    echo "" >> "$temp_file"
    echo "---" >> "$temp_file"
    echo "" >> "$temp_file"
done

# Use Claude to synthesize combined release notes
print_status "Synthesizing combined release notes..."

claude_prompt="You are provided with individual release notes for multiple packages in a monorepo.

Generate a comprehensive release notes document that:

1. Starts with an executive summary of the overall release
2. Groups related changes across packages when applicable
3. Highlights any breaking changes prominently
4. Organizes changes by category (Features, Bug Fixes, Improvements, Breaking Changes, etc.)
5. Maintains package-specific details where necessary
6. Includes the line change statistics from each package

Use markdown formatting with appropriate headers and structure.

The output should be ready to use as release notes for the entire monorepo release.

Rules:
- Start with # Release Notes followed by the version/date
- Use ## for major sections (Summary, Breaking Changes, Features, etc.)
- Use ### for package-specific subsections when needed
- Include statistics in a readable format
- Be concise but comprehensive
- Highlight breaking changes at the top if any exist

Generate the complete release notes document."

release_notes=$(claude --print "$claude_prompt" < "$temp_file" 2>&1)

# Cleanup
rm -f "$temp_file"

# Output the combined release notes
echo "$release_notes"

print_status "Release notes generation complete!" >&2
