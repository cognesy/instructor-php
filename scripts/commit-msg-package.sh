#!/bin/bash

# Generate conventional commit message for a specific package using Claude CLI
# Usage: ./scripts/generate-package-commit-message.sh <package-name>
# Example: ./scripts/generate-package-commit-message.sh schema

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

# Get package source file changes with line counts (both staged and unstaged)
get_package_changes() {
    local package="$1"
    # Combine staged and unstaged changes
    {
        # Staged changes (non-deleted files)
        git diff --cached --name-status | \
        grep -v "^D" | \
        grep "packages/$package/" | \
        while read status file; do
            local lines=$(git diff --cached --numstat "$file" | awk '{print ($1 + $2)}')
            echo "$lines $file"
        done

        # Unstaged changes (modified files, not untracked)
        git diff --name-status | \
        grep -v "^D" | \
        grep "packages/$package/" | \
        while read status file; do
            local lines=$(git diff --numstat "$file" | awk '{print ($1 + $2)}')
            echo "$lines $file"
        done

        # Untracked files in package (not ignored)
        git ls-files --others --exclude-standard | \
        grep "packages/$package/" | \
        while read file; do
            if [ -f "$file" ]; then
                local lines=$(wc -l < "$file" 2>/dev/null || echo "0")
                echo "$lines $file"
            fi
        done
    } | sort -rn
}

# Get top files in package by line changes
get_top_files_in_package() {
    local package="$1"
    local limit="${2:-3}"
    get_package_changes "$package" | head -"$limit" | awk '{print $2}'
}

# Generate commit message using Claude CLI
generate_commit_message() {
    local package="$1"
    local package_changes=$(get_package_changes "$package")

    if [ -z "$package_changes" ]; then
        print_warning "No changes detected in package '$package'"
        exit 1
    fi

    local total_lines=$(echo "$package_changes" | awk '{sum+=$1} END {print sum}')
    local analysis_scope=""

    print_status "Analyzing changes in package: $package ($total_lines lines changed)"

    # Build analysis scope
    if [ "$total_lines" -gt 100 ]; then
        # Too many changes, focus on top 3 files
        print_status "Package has $total_lines lines changed, focusing on top files..."
        analysis_scope=$(get_top_files_in_package "$package" 3)
    else
        # Include all files from this package
        analysis_scope=$(echo "$package_changes" | awk '{print $2}')
    fi

    # Create temporary file for Claude analysis
    local temp_file=$(mktemp)

    # Generate diff for analysis scope
    if [ ! -z "$analysis_scope" ]; then
        echo "=== GIT DIFF FOR CHANGES ===" > "$temp_file"
        for file in $analysis_scope; do
            echo "--- File: $file ---" >> "$temp_file"

            # Try staged diff first
            if git diff --cached --name-only | grep -q "^$file$"; then
                git diff --cached "$file" >> "$temp_file" 2>/dev/null
            # Then unstaged diff
            elif git diff --name-only | grep -q "^$file$"; then
                git diff "$file" >> "$temp_file" 2>/dev/null
            # Then show untracked file content (first 50 lines)
            elif [ -f "$file" ]; then
                echo "New file:" >> "$temp_file"
                head -50 "$file" >> "$temp_file" 2>/dev/null
            fi
            echo "" >> "$temp_file"
        done
    fi

    echo "=== ALL CHANGES IN PACKAGE ===" >> "$temp_file"
    echo "Staged files:" >> "$temp_file"
    git diff --cached --name-status | grep "packages/$package/" >> "$temp_file" || echo "None" >> "$temp_file"
    echo "Unstaged files:" >> "$temp_file"
    git diff --name-status | grep "packages/$package/" >> "$temp_file" || echo "None" >> "$temp_file"
    echo "Untracked files:" >> "$temp_file"
    git ls-files --others --exclude-standard | grep "packages/$package/" >> "$temp_file" || echo "None" >> "$temp_file"

    # Use Claude to analyze and generate commit message
    print_status "Using Claude CLI to analyze changes and generate commit message..."

    local claude_prompt="Analyze the following git changes in the '$package' package to generate a conventional commit message.

Focus on:
1. The main purpose/type of changes (feat, fix, refactor, docs, test, etc.)
2. The scope is already known: '$package'
3. A clear, specific and concise description of what was changed/added/fixed

Rules:
- Be specific - refer to component or classes changed within the package
- Use conventional commits format: type($package): description
- Keep description under 60 characters
- Focus on the most significant changes
- Common types: feat (new feature), fix (bug fix), refactor (code restructure), docs (documentation), test (testing), chore (maintenance)

Respond with ONLY the commit message, no explanation."

    local commit_message
    local claude_output
    claude_output=$(claude --print "$claude_prompt" < "$temp_file" 2>&1)
    local claude_exit_code=$?
    commit_message=$(echo "$claude_output" | tail -1 | tr -d '\n\r' | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')

    # Cleanup
    rm -f "$temp_file"

    # Validate commit message format
    if [[ "$commit_message" =~ ^[a-z]+(\([a-z0-9-]+\))?:[[:space:]].+ ]] && [ ${#commit_message} -lt 100 ]; then
        echo "$commit_message"
    else
        print_warning "Claude generated invalid format: '$commit_message', using fallback..."
        generate_fallback_commit_message "$package"
    fi
}

# Fallback commit message generation
generate_fallback_commit_message() {
    local package="$1"
    local all_files=$(get_package_changes "$package" | awk '{print $2}')
    local added_files=$(git diff --cached --diff-filter=A --name-only | grep "packages/$package/"; git ls-files --others --exclude-standard | grep "packages/$package/")
    local modified_files=$(git diff --cached --diff-filter=M --name-only | grep "packages/$package/"; git diff --name-only | grep "packages/$package/")
    local deleted_files=$(git diff --cached --diff-filter=D --name-only | grep "packages/$package/")

    local commit_type="feat"
    local description=""

    # Pattern analysis
    if echo "$all_files" | grep -q "\.md$\|README"; then
        commit_type="docs"
        description="update documentation"
    elif echo "$all_files" | grep -q "test\|spec"; then
        commit_type="test"
        description="update tests"
    elif [ ! -z "$deleted_files" ] && [ -z "$added_files" ]; then
        commit_type="refactor"
        description="remove unused files and clean up"
    elif [ ! -z "$added_files" ]; then
        commit_type="feat"
        description="add new functionality"
    elif [ ! -z "$modified_files" ]; then
        commit_type="fix"
        description="update existing functionality"
    fi

    echo "$commit_type($package): $description"
}

# Generate and output commit message
commit_message=$(generate_commit_message "$PACKAGE_NAME")
echo "$commit_message"
