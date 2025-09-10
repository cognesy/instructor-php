#!/bin/bash

# Auto-commit script that generates conventional commit messages
# Based on git status and changes analysis

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

# Check if there are any changes to commit
if git diff --quiet && git diff --cached --quiet && [ -z "$(git ls-files --others --exclude-standard)" ]; then
    print_warning "No changes to commit"
    exit 0
fi

# Add all changes (modified, deleted, and untracked files)
print_status "Adding all changes to staging area..."
git add -A

# Analyze changes to determine commit type and scope
generate_commit_message() {
    local staged_files=$(git diff --cached --name-only)
    local added_files=$(git diff --cached --diff-filter=A --name-only)
    local modified_files=$(git diff --cached --diff-filter=M --name-only)
    local deleted_files=$(git diff --cached --diff-filter=D --name-only)
    local renamed_files=$(git diff --cached --diff-filter=R --name-only)
    
    local commit_type="feat"
    local scope=""
    local description=""
    
    # Analyze file patterns to determine type and scope
    if echo "$staged_files" | grep -q "test\|spec"; then
        if [ $(echo "$staged_files" | wc -l) -eq $(echo "$staged_files" | grep -c "test\|spec") ]; then
            commit_type="test"
        fi
    fi
    
    if echo "$staged_files" | grep -q "\.md$\|docs/\|README"; then
        if [ $(echo "$staged_files" | wc -l) -eq $(echo "$staged_files" | grep -c "\.md$\|docs/\|README") ]; then
            commit_type="docs"
        fi
    fi
    
    if [ ! -z "$deleted_files" ] && [ -z "$added_files" ]; then
        commit_type="refactor"
        description="remove unused files and clean up codebase"
    elif [ ! -z "$renamed_files" ]; then
        commit_type="refactor"
        description="reorganize and restructure components"
    elif [ ! -z "$modified_files" ] && [ -z "$added_files" ]; then
        if echo "$modified_files" | grep -q "packages/messages"; then
            commit_type="refactor"
            scope="messages"
            description="improve MessageStore implementation"
        elif echo "$modified_files" | grep -q "packages/"; then
            commit_type="fix"
            description="update package implementations"
        else
            commit_type="fix"
            description="update existing functionality"
        fi
    elif [ ! -z "$added_files" ]; then
        if echo "$added_files" | grep -q "packages/messages.*Operator"; then
            commit_type="feat"
            scope="messages"
            description="add new operator classes for MessageStore"
        else
            commit_type="feat"
            description="add new functionality"
        fi
    fi
    
    # Detect specific patterns
    if echo "$staged_files" | grep -q "docs-build/"; then
        if [ $(echo "$staged_files" | wc -l) -gt 10 ]; then
            commit_type="docs"
            description="update documentation content across multiple sections"
        else
            commit_type="docs"
            description="update documentation"
        fi
    fi
    
    if echo "$staged_files" | grep -q "MessageStore\|messages/"; then
        scope="messages"
        if [ "$commit_type" = "feat" ]; then
            description="enhance MessageStore functionality"
        elif [ "$commit_type" = "refactor" ]; then
            description="refactor MessageStore architecture"
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