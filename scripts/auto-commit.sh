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

# Generate commit message by calling the separate script
generate_commit_message() {
    local script_dir="$(dirname "$0")"
    "$script_dir/generate-commit-message.sh"
}

# Generate commit message
commit_message=$(generate_commit_message 2>/dev/null)
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