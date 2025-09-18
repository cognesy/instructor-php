#!/bin/bash

# Generate conventional commit message using Claude CLI
# Analyzes git changes and generates appropriate commit message

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

# Get package source file changes with line counts (both staged and unstaged)
get_package_src_changes() {
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

# Get top files in a package by line changes (both staged and unstaged)
get_top_files_in_package() {
    local package="$1"
    # Combine staged, unstaged, and untracked files
    {
        # Staged changes
        git diff --cached --name-status | \
        grep -v "^D" | \
        grep "packages/$package/src" | \
        while read status file; do
            local lines=$(git diff --cached --numstat "$file" | awk '{print ($1 + $2)}')
            echo "$lines $file"
        done
        
        # Unstaged changes
        git diff --name-status | \
        grep -v "^D" | \
        grep "packages/$package/src" | \
        while read status file; do
            local lines=$(git diff --numstat "$file" | awk '{print ($1 + $2)}')
            echo "$lines $file"
        done
        
        # Untracked files
        git ls-files --others --exclude-standard | \
        grep "packages/$package/src" | \
        while read file; do
            if [ -f "$file" ]; then
                local lines=$(wc -l < "$file" 2>/dev/null || echo "0")
                echo "$lines $file"
            fi
        done
    } | \
    sort -rn | \
    head -3 | \
    awk '{print $2}'
}

# Generate commit message using Claude CLI
generate_commit_message() {
    local package_changes=$(get_package_src_changes)
    
    if [ -z "$package_changes" ]; then
        # No package source changes - use fallback analysis
        print_status "No package source changes detected, using basic analysis..."
        generate_fallback_commit_message
        return
    fi
    
    # Get top 3 packages with most changes
    local top_packages=$(echo "$package_changes" | head -3 | awk '{print $2}')
    local analysis_scope=""
    
    print_status "Analyzing changes in packages: $(echo $top_packages | tr '\n' ' ')"
    
    # Build analysis scope
    for package in $top_packages; do
        local package_line_count=$(echo "$package_changes" | grep "^[0-9]* $package " | awk '{print $1}')
        
        if [ "$package_line_count" -gt 100 ]; then
            # Too many changes, focus on top 3 files
            print_status "Package $package has $package_line_count lines changed, focusing on top files..."
            local top_files=$(get_top_files_in_package "$package")
            for file in $top_files; do
                analysis_scope="$analysis_scope $file"
            done
        else
            # Include all files from this package
            local package_files=$(git diff --cached --name-only | grep "packages/$package/src")
            analysis_scope="$analysis_scope $package_files"
        fi
    done
    
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
    
    echo "=== ALL CHANGES ===" >> "$temp_file"
    echo "Staged files:" >> "$temp_file"
    git diff --cached --name-status >> "$temp_file"
    echo "Unstaged files:" >> "$temp_file"
    git diff --name-status >> "$temp_file"
    echo "Untracked files (in packages/*/src):" >> "$temp_file"
    git ls-files --others --exclude-standard | grep "packages/[^/]*/src" >> "$temp_file"
    
    # Use Claude to analyze and generate commit message
    print_status "Using Claude CLI to analyze changes and generate commit message..."
    
    local claude_prompt="Analyze the following git changes (staged, unstaged, and untracked files) to generate a conventional commit message.

Focus on:
1. The main purpose/type of changes (feat, fix, refactor, docs, test, etc.)
2. The scope (which package/component/classes is primarily affected)
3. A clear, specific and concise description of what was changed/added/fixed

Rules:
- Be specific - refer to packages, component or classes changed
- Use conventional commits format: type(scope): description
- Keep description under 60 characters
- Focus on the most significant changes
- If multiple packages affected, use the most impactful one as scope
- Common types: feat (new feature), fix (bug fix), refactor (code restructure), docs (documentation), test (testing), chore (maintenance)

Respond with ONLY the commit message, no explanation."
    
    local commit_message
    local claude_output
    local claude_stderr
    claude_output=$(claude --print "$claude_prompt" < "$temp_file" 2>&1)
    local claude_exit_code=$?
    commit_message=$(echo "$claude_output" | tail -1 | tr -d '\n\r' | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
    
    # Optional debug output (uncomment for troubleshooting)
    # print_status "Claude exit code: $claude_exit_code" >&2
    # print_status "Claude raw output: $claude_output" >&2
    # print_status "Extracted message: '$commit_message'" >&2
    
    # Cleanup
    rm -f "$temp_file"
    
    # Validate commit message format - more flexible regex
    if [[ "$commit_message" =~ ^[a-z]+(\([a-z0-9-]+\))?:[[:space:]].+ ]] && [ ${#commit_message} -lt 100 ]; then
        echo "$commit_message"
    else
        print_warning "Claude generated invalid format: '$commit_message', using fallback..."
        generate_fallback_commit_message
    fi
}

# Fallback commit message generation (enhanced logic for all changes)
generate_fallback_commit_message() {
    local all_files=""
    local added_files=""
    local modified_files=""
    local deleted_files=""
    local renamed_files=""
    
    # Collect all changes (staged, unstaged, untracked)
    all_files=$(git diff --cached --name-only; git diff --name-only; git ls-files --others --exclude-standard | grep "packages/[^/]*/src")
    added_files=$(git diff --cached --diff-filter=A --name-only; git ls-files --others --exclude-standard | grep "packages/[^/]*/src")
    modified_files=$(git diff --cached --diff-filter=M --name-only; git diff --name-only)
    deleted_files=$(git diff --cached --diff-filter=D --name-only)
    renamed_files=$(git diff --cached --diff-filter=R --name-only)
    
    local commit_type="feat"
    local scope=""
    local description=""
    
    # Detect package-specific changes
    if echo "$all_files" | grep -q "packages/messages"; then
        scope="messages"
        if echo "$deleted_files" | grep -q "packages/messages" && echo "$modified_files" | grep -q "packages/messages"; then
            commit_type="refactor"
            description="restructure MessageStore architecture"
        elif echo "$modified_files" | grep -q "packages/messages"; then
            commit_type="fix"
            description="improve MessageStore implementation"
        fi
    elif echo "$all_files" | grep -q "packages/"; then
        local main_package=$(echo "$all_files" | grep "packages/" | head -1 | sed 's|packages/\([^/]*\)/.*|\1|')
        scope="$main_package"
    fi
    
    # Basic pattern analysis
    if echo "$all_files" | grep -q "\.md$\|docs-build/\|README"; then
        if [ $(echo "$all_files" | wc -l) -eq $(echo "$all_files" | grep -c "\.md$\|docs-build/\|README") ]; then
            commit_type="docs"
            description="update documentation"
        fi
    fi
    
    if echo "$all_files" | grep -q "test\|spec"; then
        if [ $(echo "$all_files" | wc -l) -eq $(echo "$all_files" | grep -c "test\|spec") ]; then
            commit_type="test"
            description="update tests"
        fi
    fi
    
    # Default descriptions based on change type
    if [ -z "$description" ]; then
        if [ ! -z "$deleted_files" ] && [ -z "$added_files" ]; then
            commit_type="refactor"
            description="remove unused files and clean up codebase"
        elif [ ! -z "$renamed_files" ]; then
            commit_type="refactor"
            description="reorganize and restructure components"
        elif [ ! -z "$added_files" ]; then
            commit_type="feat"
            description="add new functionality"
        elif [ ! -z "$modified_files" ]; then
            commit_type="fix"
            description="update existing functionality"
        fi
    fi
    
    # Build commit message
    local message="$commit_type"
    if [ ! -z "$scope" ]; then
        message="$message($scope)"
    fi
    message="$message: $description"
    
    echo "$message"
}

# Check if there are any changes at all
if git diff --quiet && git diff --cached --quiet && [ -z "$(git ls-files --others --exclude-standard | grep 'packages/[^/]*/src')" ]; then
    print_warning "No changes found in the repository."
    exit 1
fi

# Generate and output commit message
commit_message=$(generate_commit_message)
echo "$commit_message"