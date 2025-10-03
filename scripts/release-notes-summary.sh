#!/bin/bash

# Generate release notes summary for a package using Claude CLI
# Usage: ./scripts/release-notes-summary.sh <package-name>
# Example: ./scripts/release-notes-summary.sh schema

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

# Check if claude CLI is available
if ! command -v claude >/dev/null 2>&1; then
    print_error "Claude CLI not found. Please install claude CLI first."
    exit 1
fi

# Check if package exists
if [ ! -d "packages/$PACKAGE_NAME" ]; then
    print_error "Package 'packages/$PACKAGE_NAME' does not exist"
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
else
    print_status "Latest tag: $latest_tag"
    from_ref="$latest_tag"
fi

# Check if there are changes
if git diff --quiet "$from_ref"..HEAD -- "packages/$PACKAGE_NAME/"; then
    print_warning "No changes found in package '$PACKAGE_NAME' since $from_ref"
    exit 1
fi

print_status "Analyzing changes in package '$PACKAGE_NAME' since $from_ref"

# Get statistics
stats=$(git diff --numstat "$from_ref"..HEAD -- "packages/$PACKAGE_NAME/" | \
    awk '{added+=$1; removed+=$2; files++} END {print added+removed, added, removed, files}')

total_lines=$(echo "$stats" | awk '{print $1}')
added_lines=$(echo "$stats" | awk '{print $2}')
removed_lines=$(echo "$stats" | awk '{print $3}')
changed_files=$(echo "$stats" | awk '{print $4}')

print_status "Statistics: $total_lines lines changed (+$added_lines/-$removed_lines) in $changed_files files"

# Create temporary file for Claude analysis
temp_file=$(mktemp)

echo "=== PACKAGE CHANGES SUMMARY ===" > "$temp_file"
echo "Package: $PACKAGE_NAME" >> "$temp_file"
echo "Since: $from_ref" >> "$temp_file"
echo "Total lines changed: $total_lines (+$added_lines/-$removed_lines)" >> "$temp_file"
echo "Files changed: $changed_files" >> "$temp_file"
echo "" >> "$temp_file"

echo "=== FILE CHANGES ===" >> "$temp_file"
git diff --name-status "$from_ref"..HEAD -- "packages/$PACKAGE_NAME/" >> "$temp_file"
echo "" >> "$temp_file"

echo "=== DETAILED CHANGES ===" >> "$temp_file"

# If total changes are large, show only top files
if [ "$total_lines" -gt 500 ]; then
    print_status "Large changeset detected. Analyzing top changed files..."

    # Get top 5 most changed files
    top_files=$(git diff --numstat "$from_ref"..HEAD -- "packages/$PACKAGE_NAME/" | \
        awk '{print ($1+$2), $3}' | sort -rn | head -5 | awk '{print $2}')

    echo "Top changed files (showing sample):" >> "$temp_file"
    for file in $top_files; do
        echo "--- File: $file ---" >> "$temp_file"
        git diff "$from_ref"..HEAD -- "$file" | head -100 >> "$temp_file"
        echo "" >> "$temp_file"
    done
else
    # Show all changes for smaller changesets
    echo "Complete diff:" >> "$temp_file"
    git diff "$from_ref"..HEAD -- "packages/$PACKAGE_NAME/" >> "$temp_file"
fi

# Use Claude to analyze and generate release notes summary
print_status "Using Claude CLI to generate release notes summary..."

claude_prompt="Analyze the following git changes for the '$PACKAGE_NAME' package to generate release notes.

The changes include $total_lines lines (+$added_lines/-$removed_lines) across $changed_files files.

Generate a concise release notes summary with the following structure:

## Changes in $PACKAGE_NAME ($total_lines lines changed)

### Summary
[One paragraph overview of the main changes]

### Key Changes
- [List 3-5 most important changes as bullet points]
- [Focus on user-facing changes and API modifications]
- [Include breaking changes if any]

### Details
[Additional details about implementation changes if significant]

Rules:
- Be specific about what changed (classes, methods, features)
- Highlight breaking changes prominently
- Focus on developer-facing impact
- Keep it concise but informative
- Use markdown formatting

Respond with the complete release notes in markdown format."

release_notes=$(claude --print "$claude_prompt" < "$temp_file" 2>&1)

# Cleanup
rm -f "$temp_file"

# Output the release notes
echo "$release_notes"
