#!/bin/bash

# Auto-commit script that generates conventional commit messages
# Uses Claude CLI agent to analyze changes intelligently

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
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

# Check if there are any changes to commit
if git diff --quiet && git diff --cached --quiet && [ -z "$(git ls-files --others --exclude-standard)" ]; then
    print_warning "No changes to commit"
    exit 0
fi

# Add all changes (modified, deleted, and untracked files)
print_status "Adding all changes to staging area..."
git add -A

# Get package source file changes with line counts
get_package_src_changes() {
    git diff --cached --numstat | \
    grep "packages/[^/]*/src" | \
    awk '{
        package = gensub(/packages\/([^\/]*)\/src\/.*/, "\\1", "g", $3)
        files[package]++
        lines[package] += ($1 + $2)
    } END {
        for (p in files) {
            print lines[p], p, files[p]
        }
    }' | sort -rn
}

# Get top files in a package by line changes
get_top_files_in_package() {
    local package="$1"
    git diff --cached --numstat | \
    grep "packages/$package/src" | \
    awk '{print ($1 + $2), $3}' | \
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
        echo "=== GIT DIFF FOR STAGED CHANGES ===" > "$temp_file"
        for file in $analysis_scope; do
            echo "--- File: $file ---" >> "$temp_file"
            git diff --cached "$file" >> "$temp_file"
            echo "" >> "$temp_file"
        done
    fi
    
    echo "=== ALL STAGED FILES ===" >> "$temp_file"
    git diff --cached --name-status >> "$temp_file"
    
    # Use Claude to analyze and generate commit message
    print_status "Using Claude CLI to analyze changes and generate commit message..."
    
    local claude_prompt="Analyze the following git diff and staged files to generate a conventional commit message.

Focus on:
1. The main purpose/type of changes (feat, fix, refactor, docs, test, etc.)
2. The scope (which package/component is primarily affected)
3. A clear, concise description of what was changed/added/fixed

Rules:
- Use conventional commits format: type(scope): description
- Keep description under 60 characters
- Focus on the most significant changes
- If multiple packages affected, use the most impactful one as scope
- Common types: feat (new feature), fix (bug fix), refactor (code restructure), docs (documentation), test (testing), chore (maintenance)

Respond with ONLY the commit message, no explanation."
    
    local commit_message
    commit_message=$(claude --no-color --agent-mode "$claude_prompt" < "$temp_file" 2>/dev/null | tail -1 | tr -d '\n\r' | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
    
    # Cleanup
    rm -f "$temp_file"
    
    # Validate commit message format
    if [[ "$commit_message" =~ ^[a-z]+(\([a-z0-9-]+\))?:\ .+ ]]; then
        echo "$commit_message"
    else
        print_warning "Claude generated invalid format, using fallback..."
        generate_fallback_commit_message
    fi
}

# Fallback commit message generation (original logic)
generate_fallback_commit_message() {
    local staged_files=$(git diff --cached --name-only)
    local added_files=$(git diff --cached --diff-filter=A --name-only)
    local modified_files=$(git diff --cached --diff-filter=M --name-only)
    local deleted_files=$(git diff --cached --diff-filter=D --name-only)
    local renamed_files=$(git diff --cached --diff-filter=R --name-only)
    
    local commit_type="feat"
    local scope=""
    local description=""
    
    # Basic pattern analysis
    if echo "$staged_files" | grep -q "\.md$\|docs-build/\|README"; then
        if [ $(echo "$staged_files" | wc -l) -eq $(echo "$staged_files" | grep -c "\.md$\|docs-build/\|README") ]; then
            commit_type="docs"
            description="update documentation"
        fi
    fi
    
    if echo "$staged_files" | grep -q "test\|spec"; then
        if [ $(echo "$staged_files" | wc -l) -eq $(echo "$staged_files" | grep -c "test\|spec") ]; then
            commit_type="test"
            description="update tests"
        fi
    fi
    
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
    
    # Build commit message
    local message="$commit_type"
    if [ ! -z "$scope" ]; then
        message="$message($scope)"
    fi
    message="$message: $description"
    
    echo "$message"
}

# Generate commit message
commit_message=$(generate_commit_message)
print_status "Generated commit message: $commit_message"

# Show what will be committed
print_status "Files to be committed:"
git diff --cached --name-status | while read status file; do
    case $status in
        A) echo -e "  ${GREEN}added:${NC}    $file" ;;
        M) echo -e "  ${YELLOW}modified:${NC} $file" ;;
        D) echo -e "  ${RED}deleted:${NC}  $file" ;;
        R*) echo -e "  ${YELLOW}renamed:${NC}  $file" ;;
        *) echo -e "  $status:      $file" ;;
    esac
done

# Ask for confirmation if running interactively
if [ -t 1 ]; then
    echo
    read -p "Proceed with commit? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warning "Commit cancelled"
        exit 0
    fi
fi

# Commit the changes
print_status "Creating commit..."
git commit -m "$commit_message"

if [ $? -eq 0 ]; then
    print_status "Successfully committed changes!"
    print_status "Commit message: $commit_message"
else
    print_error "Failed to create commit"
    exit 1
fi