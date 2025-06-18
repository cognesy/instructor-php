#!/usr/bin/env bash
# split-packages.sh - Push package changes to read-only repositories without versioning
set -e

# Configuration
GITHUB_TOKEN="${GITHUB_TOKEN:-$SPLIT_TOKEN_4}"
GITHUB_ORG="cognesy"
GITHUB_USER="ddebowczyk"
GITHUB_EMAIL="ddebowczyk@gmail.com"
TARGET_BRANCH="main"

# Package definitions (extracted from split.yml)
declare -A PACKAGES
PACKAGES["packages/addons"]="instructor-addons"
PACKAGES["packages/auxiliary"]="instructor-aux"
PACKAGES["packages/config"]="instructor-config"
PACKAGES["packages/evals"]="instructor-evals"
PACKAGES["packages/events"]="instructor-events"
PACKAGES["packages/http-client"]="instructor-http-client"
PACKAGES["packages/hub"]="instructor-hub"
PACKAGES["packages/instructor"]="instructor-struct"
PACKAGES["packages/polyglot"]="instructor-polyglot"
PACKAGES["packages/schema"]="instructor-schema"
PACKAGES["packages/schema-v6"]="instructor-schema-v6"
PACKAGES["packages/setup"]="instructor-setup"
PACKAGES["packages/tell"]="instructor-tell"
PACKAGES["packages/templates"]="instructor-templates"
PACKAGES["packages/utils"]="instructor-utils"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check dependencies
check_dependencies() {
    local missing_deps=()

    if ! command -v git &> /dev/null; then
        missing_deps+=("git")
    fi

    if ! command -v splitsh-lite &> /dev/null; then
        log_warning "splitsh-lite not found. Installing..."
        if command -v brew &> /dev/null; then
            brew install splitsh/tap/lite
        elif command -v curl &> /dev/null; then
            curl -L https://github.com/splitsh/lite/releases/latest/download/lite_linux_amd64.tar.gz | tar -xz
            sudo mv splitsh-lite /usr/local/bin/
        else
            missing_deps+=("splitsh-lite")
        fi
    fi

    if [ ${#missing_deps[@]} -ne 0 ]; then
        log_error "Missing dependencies: ${missing_deps[*]}"
        log_info "Please install missing dependencies and try again"
        exit 1
    fi
}

# Validate GitHub token
check_github_token() {
    if [ -z "$GITHUB_TOKEN" ]; then
        log_error "GitHub token not found!"
        log_info "Set GITHUB_TOKEN or SPLIT_TOKEN_4 environment variable"
        log_info "Example: export GITHUB_TOKEN=your_token_here"
        exit 1
    fi
}

# Show usage information
show_usage() {
    cat << EOF
Usage: $0 [OPTIONS] [PACKAGE_NAMES...]

Push package changes to read-only repositories without creating a new version.

OPTIONS:
    -h, --help          Show this help message
    -l, --list          List available packages
    -b, --branch BRANCH Target branch (default: main)
    -d, --dry-run       Show what would be done without executing
    --all               Process all packages (default if no packages specified)

PACKAGE_NAMES:
    Space-separated list of package directory names (e.g., packages/utils packages/events)
    Or just the package names (e.g., utils events)

EXAMPLES:
    $0 --list                          # List all available packages
    $0 utils events                    # Split only utils and events packages
    $0 packages/utils packages/events  # Same as above
    $0 --all                           # Split all packages
    $0 --dry-run utils                 # Show what would be done for utils package

ENVIRONMENT VARIABLES:
    GITHUB_TOKEN or SPLIT_TOKEN_4      GitHub personal access token with repo permissions
EOF
}

# List available packages
list_packages() {
    log_info "Available packages:"
    for local_path in "${!PACKAGES[@]}"; do
        repo_name="${PACKAGES[$local_path]}"
        package_name=$(basename "$local_path")
        echo "  • $package_name ($local_path) → $GITHUB_ORG/$repo_name"
    done
}

# Normalize package name to full path
normalize_package_name() {
    local input="$1"

    # If already full path, return as-is
    if [[ "$input" == packages/* ]]; then
        echo "$input"
        return
    fi

    # If just package name, convert to full path
    echo "packages/$input"
}

# Split a single package
split_package() {
    local local_path="$1"
    local repo_name="${PACKAGES[$local_path]}"
    local package_name=$(basename "$local_path")
    local dry_run="$2"

    if [ -z "$repo_name" ]; then
        log_error "Package $local_path not found in configuration"
        return 1
    fi

    if [ ! -d "$local_path" ]; then
        log_error "Directory $local_path does not exist"
        return 1
    fi

    log_info "Processing $package_name ($local_path → $GITHUB_ORG/$repo_name)"

    if [ "$dry_run" = "true" ]; then
        log_info "[DRY RUN] Would split $local_path to $GITHUB_ORG/$repo_name"
        return 0
    fi

    # Create split using splitsh-lite
    local split_sha
    split_sha=$(splitsh-lite --prefix="$local_path")

    if [ -z "$split_sha" ]; then
        log_error "Failed to create split for $local_path"
        return 1
    fi

    log_info "Split SHA: $split_sha"

    # Configure git
    git config user.name "$GITHUB_USER"
    git config user.email "$GITHUB_EMAIL"

    # Push to target repository
    local remote_url="https://$GITHUB_TOKEN@github.com/$GITHUB_ORG/$repo_name.git"

    log_info "Pushing to $GITHUB_ORG/$repo_name..."

    if git push "$remote_url" "$split_sha:refs/heads/$TARGET_BRANCH" 2>/dev/null; then
        log_success "Successfully pushed $package_name to $GITHUB_ORG/$repo_name"
    else
        log_error "Failed to push $package_name to $GITHUB_ORG/$repo_name"
        return 1
    fi
}

# Main function
main() {
    local packages_to_process=()
    local dry_run="false"
    local target_branch="main"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_usage
                exit 0
                ;;
            -l|--list)
                list_packages
                exit 0
                ;;
            -b|--branch)
                target_branch="$2"
                shift 2
                ;;
            -d|--dry-run)
                dry_run="true"
                shift
                ;;
            --all)
                packages_to_process=("${!PACKAGES[@]}")
                shift
                ;;
            -*)
                log_error "Unknown option: $1"
                show_usage
                exit 1
                ;;
            *)
                packages_to_process+=("$(normalize_package_name "$1")")
                shift
                ;;
        esac
    done

    # Set target branch
    TARGET_BRANCH="$target_branch"

    # If no packages specified, process all
    if [ ${#packages_to_process[@]} -eq 0 ]; then
        packages_to_process=("${!PACKAGES[@]}")
        log_info "No packages specified, processing all packages"
    fi

    # Check dependencies and token
    check_dependencies
    check_github_token

    # Ensure we're in a git repository
    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        log_error "Not in a git repository"
        exit 1
    fi

    # Check for uncommitted changes
    if [ "$dry_run" = "false" ] && ! git diff-index --quiet HEAD --; then
        log_warning "You have uncommitted changes. Consider committing them first."
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi

    log_info "Starting package split process..."
    log_info "Target branch: $TARGET_BRANCH"
    log_info "Packages to process: ${#packages_to_process[@]}"

    if [ "$dry_run" = "true" ]; then
        log_warning "DRY RUN MODE - No changes will be made"
    fi

    # Process each package
    local success_count=0
    local error_count=0

    for local_path in "${packages_to_process[@]}"; do
        if split_package "$local_path" "$dry_run"; then
            ((success_count++))
        else
            ((error_count++))
        fi
        echo # Add spacing between packages
    done

    # Summary
    log_info "Split process completed"
    log_success "Successfully processed: $success_count packages"
    if [ $error_count -gt 0 ]; then
        log_error "Failed to process: $error_count packages"
        exit 1
    fi
}

# Run main function with all arguments
main "$@"