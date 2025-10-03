#!/bin/bash

# Generate conventional commit message for all changed packages using Claude CLI
# Analyzes all package changes and generates appropriate commit message

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

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Get all changed packages with line counts (both staged and unstaged)
get_all_package_changes() {
    # Combine staged and unstaged changes
    {
        # Staged changes (non-deleted files)
        git diff --cached --name-status | \
        grep -v "^D" | \
        grep "packages/[^/]*/src" | \
        while read status file; do
            local lines=$(git diff --cached --numstat "$file" | awk '{print ($1 + $2)}')
            local package=$(echo "$file" | sed 's|packages/\([^/]*\)/src/.*|\1|')
            echo "$lines $package $file"
        done

        # Unstaged changes (modified files, not untracked)
        git diff --name-status | \
        grep -v "^D" | \
        grep "packages/[^/]*/src" | \
        while read status file; do
            local lines=$(git diff --numstat "$file" | awk '{print ($1 + $2)}')
            local package=$(echo "$file" | sed 's|packages/\([^/]*\)/src/.*|\1|')
            echo "$lines $package $file"
        done

        # Untracked files in packages/*/src (not ignored)
        git ls-files --others --exclude-standard | \
        grep "packages/[^/]*/src" | \
        while read file; do
            if [ -f "$file" ]; then
                local lines=$(wc -l < "$file" 2>/dev/null || echo "0")
                local package=$(echo "$file" | sed 's|packages/\([^/]*\)/src/.*|\1|')
                echo "$lines $package $file"
            fi
        done
    } | \
    awk '{
        lines[$(NF-1)] += $1
        files[$(NF-1)]++
    } END {
        for (p in files) {
            print lines[p], p, files[p]
        }
    }' | sort -rn
}

# Check if there are any changes at all
if git diff --quiet && git diff --cached --quiet && [ -z "$(git ls-files --others --exclude-standard | grep 'packages/[^/]*/src')" ]; then
    print_warning "No changes found in any package."
    exit 1
fi

# Get package changes
package_changes=$(get_all_package_changes)

if [ -z "$package_changes" ]; then
    print_warning "No package source changes detected."
    exit 1
fi

# Count number of changed packages
num_packages=$(echo "$package_changes" | wc -l)

if [ "$num_packages" -eq 1 ]; then
    # Single package changed - use the package-specific script
    package_name=$(echo "$package_changes" | awk '{print $2}')
    print_status "Single package changed: $package_name"
    print_status "Using package-specific commit message generation..."

    "$SCRIPT_DIR/commit-msg-package.sh" "$package_name"
else
    # Multiple packages changed - generate combined message
    print_status "Multiple packages changed ($num_packages packages)"
    print_status "Generating combined commit message..."

    # Get top 3 packages with most changes
    top_packages=$(echo "$package_changes" | head -3)
    primary_package=$(echo "$top_packages" | head -1 | awk '{print $2}')

    print_status "Primary package: $primary_package"
    print_status "Other affected packages: $(echo "$top_packages" | tail -n +2 | awk '{print $2}' | tr '\n' ' ')"

    # Create temporary file for Claude analysis
    temp_file=$(mktemp)

    echo "=== PACKAGE CHANGES SUMMARY ===" > "$temp_file"
    echo "$package_changes" | while read lines package files; do
        echo "Package: $package - $lines lines changed in $files files" >> "$temp_file"
    done
    echo "" >> "$temp_file"

    echo "=== DETAILED CHANGES ===" >> "$temp_file"

    # For each of top 3 packages, get sample of changes
    echo "$top_packages" | while read lines package files; do
        echo "--- Package: $package ---" >> "$temp_file"

        if [ "$lines" -gt 100 ]; then
            # Show only top 2 files for large changes
            git diff --cached --name-only | grep "packages/$package/" | head -2 | while read file; do
                echo "File: $file" >> "$temp_file"
                git diff --cached "$file" 2>/dev/null | head -30 >> "$temp_file"
            done
            git diff --name-only | grep "packages/$package/" | head -2 | while read file; do
                echo "File: $file" >> "$temp_file"
                git diff "$file" 2>/dev/null | head -30 >> "$temp_file"
            done
        else
            # Show all changes for smaller packages
            git diff --cached --name-only | grep "packages/$package/" | while read file; do
                echo "File: $file" >> "$temp_file"
                git diff --cached "$file" 2>/dev/null >> "$temp_file"
            done
            git diff --name-only | grep "packages/$package/" | while read file; do
                echo "File: $file" >> "$temp_file"
                git diff "$file" 2>/dev/null >> "$temp_file"
            done
        fi
        echo "" >> "$temp_file"
    done

    # Use Claude to analyze and generate commit message
    claude_prompt="Analyze the following git changes across multiple packages in a monorepo to generate a conventional commit message.

Focus on:
1. The main purpose/type of changes (feat, fix, refactor, docs, test, etc.)
2. The primary scope (the most impacted package: $primary_package)
3. A clear, specific and concise description that captures the main change

Rules:
- Use the primary package '$primary_package' as the scope
- Use conventional commits format: type($primary_package): description
- Keep description under 60 characters
- Focus on the most significant change across all packages
- Common types: feat (new feature), fix (bug fix), refactor (code restructure), docs (documentation), test (testing), chore (maintenance)
- If changes are related across packages, describe the overarching purpose

Respond with ONLY the commit message, no explanation."

    commit_message=$(claude --print "$claude_prompt" < "$temp_file" 2>&1 | tail -1 | tr -d '\n\r' | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')

    # Cleanup
    rm -f "$temp_file"

    # Validate commit message format
    if [[ "$commit_message" =~ ^[a-z]+(\([a-z0-9-]+\))?:[[:space:]].+ ]] && [ ${#commit_message} -lt 100 ]; then
        echo "$commit_message"
    else
        print_warning "Claude generated invalid format: '$commit_message', using fallback..."
        echo "refactor($primary_package): update multiple packages"
    fi
fi
