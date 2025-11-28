# Scripts Documentation

This document describes all utility scripts in the `./scripts` directory for managing the instructor-php monorepo.

## Table of Contents

- [Commit Message Generation](#commit-message-generation)
- [LLM Context Generation](#llm-context-generation)
- [Package Management](#package-management)
- [Monorepo Configuration](#monorepo-configuration)
- [Testing & Quality](#testing--quality)
- [Version Management & Release](#version-management--release)
- [Resource Management](#resource-management)

---

## Commit Message Generation

Scripts for automating conventional commit message generation using Claude CLI.

### auto-commit.sh
**Purpose**: Complete commit automation workflow wrapper

**What it does**:
- Validates git repository and Claude CLI availability
- Stages all changes (`git add -A`)
- Calls `generate-commit-message.sh` to generate commit message
- Shows color-coded file status (added/modified/deleted)
- Prompts for user confirmation (if interactive)
- Creates the commit

**Usage**:
```bash
./scripts/auto-commit.sh
```

**Dependencies**: `generate-commit-message.sh`, `git`, `claude`

---

### generate-commit-message.sh
**Purpose**: Main AI-powered commit message generator for entire repository

**Scope**: Analyzes ALL changes across all packages

**Logic**:
- Examines staged, unstaged, AND untracked files
- Focuses on `packages/*/src` directory changes
- Identifies top 3 packages with most line changes
- For large packages (>100 lines), focuses on top 3 files only
- Uses Claude CLI to analyze diffs and generate conventional commit message
- Has sophisticated fallback logic with pattern detection

**Usage**:
```bash
./scripts/generate-commit-message.sh
# Outputs: type(scope): description
```

**Output**: Just the commit message (designed for piping)

**Fallback Behavior**: Detects documentation, test, refactor patterns and generates appropriate messages

---

### commit-msg-package.sh
**Purpose**: Generate commit message for ONE specific package

**Scope**: Only changes within the specified package

**Usage**:
```bash
./scripts/commit-msg-package.sh <package-name>
# Example:
./scripts/commit-msg-package.sh instructor
```

**Logic**:
- Similar to `generate-commit-message.sh` but package-scoped
- For >100 lines, analyzes top 3 files
- Uses Claude CLI with package-specific prompt that enforces the package as scope
- Simpler fallback generation

**Output**: `type(package): description`

---

### commit-msg-all.sh
**Purpose**: Smart router for monorepo-wide changes

**Scope**: All packages, with intelligent delegation

**Logic**:
- Detects how many packages have changes
- **If 1 package**: Delegates to `commit-msg-package.sh`
- **If multiple packages**:
  - Identifies primary package (most changes)
  - Analyzes top 3 packages
  - Uses primary package as scope
  - Generates message covering cross-package changes
- Uses Claude CLI with multi-package context

**Usage**:
```bash
./scripts/commit-msg-all.sh
```

**Output**: Combined commit message focusing on primary package

**Fallback**: `refactor(primary-package): update multiple packages`

---

## LLM Context Generation

Scripts for generating markdown contexts from codebase for use with LLM tools.

### code2md.sh
**Purpose**: Generate markdown contexts from codebase for LLM-based development tools

**Dependencies**: `code2prompt` ([github.com/mufeedvh/code2prompt](https://github.com/mufeedvh/code2prompt))

**What it generates**:

1. **Full Package Exports** (`./tmp/code/*.md`):
   - Complete source for each package: `instructor.md`, `polyglot.md`, etc.

2. **Focused Subsystem Exports**:
   - `utils-json-schema.md` - JSON Schema utilities
   - `utils-messages.md` - Message handling
   - `poly-inference.md` - Polyglot inference subsystem
   - `poly-embeddings.md` - Polyglot embeddings subsystem

3. **Curated Package Variants**:
   - `polyglot-minimal.md` - Only OpenAI + Gemini drivers
   - `instructor-core.md` - Core functionality without extras/events/validation
   - `http-normal.md` - HTTP client without debug middleware
   - `http-minimal.md` - HTTP client core only (no adapters/middleware)

**Usage**:
```bash
./scripts/code2md.sh
# Output: ./tmp/code/*.md files
```

**Use Cases**:
- Code analysis with LLMs
- Generating consistent code following project patterns
- Debugging and troubleshooting
- Documentation generation

---

## Package Management

Scripts for managing Composer dependencies across all packages.

### composer-install-all.sh
**Purpose**: Install dependencies in all packages

**What it does**:
- Iterates through all `packages/*/composer.json`
- Clears composer cache
- Dumps autoloader
- Runs `composer install --no-scripts --no-progress`

**Usage**:
```bash
./scripts/composer-install-all.sh
```

---

### composer-update-all.sh
**Purpose**: Update dependencies in all packages

**What it does**:
- Iterates through all `packages/*/composer.json`
- Runs `composer update --no-scripts --no-progress -W`
- Updates all dependencies with their transitive dependencies

**Usage**:
```bash
./scripts/composer-update-all.sh
```

---

### clean-composer.sh
**Purpose**: Clean Composer caches and vendor directories

**What it does**:
- Clears composer cache for each package
- Dumps autoloader
- Removes all `vendor/*` contents

**Usage**:
```bash
./scripts/clean-composer.sh
```

**Use Case**: Clean slate before fresh install, or when dependencies are corrupted

---

### make-package
**Purpose**: Create a new subpackage from template (PHP script)

**Language**: PHP (executable script)

**What it does**:
- Takes a JSON configuration file
- Creates new package from `data/empty-new` template
- Replaces placeholders with package-specific values
- Generates proper directory structure

**Usage**:
```bash
./scripts/make-package config.json
```

**Required Config Fields**:
```json
{
  "package_name": "my-package",
  "namespace": "Cognesy\\MyPackage",
  "package_title": "My Package",
  "package_description": "Description of my package",
  "target_directory": "packages/my-package"
}
```

---

## Monorepo Configuration

Scripts for managing monorepo split configuration and package metadata.

### load-packages.sh
**Purpose**: Central package configuration loader (sourced by other scripts)

**What it does**:
- Loads `packages.json` configuration
- Provides functions for accessing package metadata
- Creates associative arrays with package information

**Functions Provided**:
- `load_packages()` - Load package data into arrays
- `get_package_dirs()` - Get all package directories
- `get_composer_name(dir)` - Get composer name for package
- `get_repo(dir)` - Get GitHub repository for package
- `get_github_name(dir)` - Get GitHub display name

**Usage** (sourced by other scripts):
```bash
source ./scripts/load-packages.sh
# Now you have access to:
# - $PACKAGES (associative array)
# - $PACKAGE_REPOS
# - $PACKAGE_GITHUB_NAMES
```

**Dependencies**: `jq`

---

### generate-split-matrix.sh
**Purpose**: Generate GitHub Actions matrix from packages.json

**What it does**:
- Reads `packages.json`
- Outputs YAML matrix entries for GitHub Actions
- Used to configure package splitting workflow

**Usage**:
```bash
./scripts/generate-split-matrix.sh [PROJECT_ROOT]
# Outputs YAML matrix to stdout
```

**Output Format**:
```yaml
# Generated from packages.json - DO NOT EDIT MANUALLY
- local: 'packages/instructor'
  repo:  'cognesy/instructor-php'
  name:  'Instructor PHP'
```

---

### update-split-yml.sh
**Purpose**: Update split.yml workflow with latest package matrix

**What it does**:
- Calls `generate-split-matrix.sh` to get new matrix
- Creates backup of existing `split.yml`
- Uses AWK to replace matrix section in workflow file
- Preserves rest of workflow structure

**Usage**:
```bash
./scripts/update-split-yml.sh [PROJECT_ROOT] [SPLIT_YML_PATH]
# Example:
./scripts/update-split-yml.sh
```

**Output**:
- Updates `.github/workflows/split.yml`
- Creates `split.yml.bak` backup

---

## Testing & Quality

Scripts for running tests and static analysis across all packages.

### run-all-tests.sh
**Purpose**: Run Pest tests in all packages

**What it does**:
- Iterates through all `packages/*/composer.json`
- Clears cache, dumps autoloader, installs dependencies
- Runs `composer test` for each package

**Usage**:
```bash
./scripts/run-all-tests.sh
```

**Exit Behavior**: Stops on first test failure (`set -e`)

---

### psalm-all.sh
**Purpose**: Run Psalm static analysis in all packages

**What it does**:
- Iterates through all `packages/*/composer.json`
- Runs `composer psalm` for each package

**Usage**:
```bash
./scripts/psalm-all.sh
```

**Exit Behavior**: Stops on first Psalm error (`set -e`)

---

## Version Management & Release

Scripts for managing versions, releases, and release notes.

### sync-ver.sh
**Purpose**: Synchronize versions across all packages for release

**What it does**:
- Updates version in all package `composer.json` files
- Updates internal dependencies to use `^MAJOR.MINOR` constraint
- Ensures consistent versioning across monorepo

**Usage**:
```bash
./scripts/sync-ver.sh <version>
# Example:
./scripts/sync-ver.sh 2.5.0
./scripts/sync-ver.sh v2.5.0  # 'v' prefix is automatically removed
```

**What gets updated**:
- Internal package dependencies in `require` and `require-dev`
- Uses `^MAJOR.MINOR` constraint (e.g., `^2.5`)

**Dependencies**: `load-packages.sh`, `jq`

---

### publish-ver.sh
**Purpose**: Main release automation script

**What it does**:
1. Validates release notes file exists (`docs/release-notes/v{VERSION}.mdx`)
2. Rebuilds documentation (Mintlify + MkDocs)
3. Copies resource files to packages
4. Updates all package versions using `sync-ver.sh`
5. Stages and commits changes
6. Creates git tag `v{VERSION}`
7. Pushes changes and tag to GitHub
8. Creates GitHub release with release notes

**Usage**:
```bash
./scripts/publish-ver.sh <version>
# Example:
./scripts/publish-ver.sh 2.5.0
```

**Prerequisites**:
- Release notes must exist at `docs/release-notes/v{VERSION}.mdx`
- GitHub CLI (`gh`) must be installed and authenticated

**Post-Release**: The `split.yml` GitHub Actions workflow automatically triggers to split packages

---

### release-notes-diff.sh
**Purpose**: Generate git diff for a package since last tagged release

**Usage**:
```bash
./scripts/release-notes-diff.sh <package-name>
# Example:
./scripts/release-notes-diff.sh instructor
```

**What it does**:
- Finds latest git tag (or first commit if no tags)
- Generates diff for specified package
- Outputs diff to stdout

**Use Case**: Manual inspection of changes before generating release notes

---

### release-notes-summary.sh
**Purpose**: Generate AI-powered release notes summary for a single package

**What it does**:
- Analyzes changes in package since last tag
- Collects statistics (lines added/removed, files changed)
- For large changesets (>500 lines), focuses on top 5 files
- Uses Claude CLI to generate structured release notes

**Usage**:
```bash
./scripts/release-notes-summary.sh <package-name>
# Example:
./scripts/release-notes-summary.sh instructor
```

**Output Format**:
```markdown
## Changes in {package} (XXX lines changed)

### Summary
[Overview paragraph]

### Key Changes
- [Bullet point changes]

### Details
[Implementation details]
```

**Dependencies**: `claude`, `git`

---

### release-notes-all.sh
**Purpose**: Generate combined release notes for all changed packages

**What it does**:
1. Finds all packages with changes since last tag
2. Generates individual summaries for each package using `release-notes-summary.sh`
3. Collects statistics for all packages
4. Uses Claude CLI to synthesize combined release notes document

**Usage**:
```bash
./scripts/release-notes-all.sh
```

**Output Structure**:
```markdown
# Release Notes

## Summary
[Executive summary]

## Breaking Changes
[If any]

## Features
[Cross-package features]

## Bug Fixes
[Fixes by package]

## Improvements
[Improvements by package]

### Package: {name}
[Package-specific details with statistics]
```

**Use Case**: Generate comprehensive release notes for entire monorepo release

**Dependencies**: `claude`, `git`, `release-notes-summary.sh`

---

## Resource Management

Scripts for managing shared resources across packages.

### copy-resources.sh
**Purpose**: Copy shared resource files to appropriate packages

**What it distributes**:
- **Config files** → To packages needing configuration
- **.env-dist** → To packages needing environment templates
- **Prompts** → To packages using prompts
- **Examples** → To hub package
- **Binaries** → To specific packages (setup, hub, tell)

**Package Mapping**:
```bash
# Defined in script:
PACKAGES_NEEDING_CONFIG=("config" "templates" "setup" "http-client" "polyglot" "instructor" "tell" "hub")
PACKAGES_NEEDING_ENV=("config" "setup" "polyglot" "instructor" "tell" "hub")
PACKAGES_NEEDING_PROMPTS=("templates" "setup" "polyglot" "instructor" "tell" "hub")
PACKAGES_NEEDING_EXAMPLES=("hub")

# Binary mapping:
instructor-setup → setup package
instructor-hub → hub package
tell → tell package
```

**Usage**:
```bash
./scripts/copy-resources.sh
```

**Use Case**: Run before publishing packages to ensure they have required resources

---

### remove-resources.sh
**Purpose**: Remove shared resource files from all packages

**What it removes**:
- Config file contents (keeps directory)
- Prompts directory
- .env-dist files
- Examples contents (keeps directory)
- Release notes contents (keeps directory)
- docs-build directory contents

**Usage**:
```bash
./scripts/remove-resources.sh
```

**Use Case**:
- Clean up before regenerating resources
- Remove stale resources from packages
- Prepare for split/distribution

**Note**: Does NOT remove bin files (by design, as some packages have package-specific binaries)

---

## Script Dependencies

### External Tools Required

- **git** - Version control operations
- **composer** - PHP dependency management
- **jq** - JSON parsing for packages.json
- **claude** - Claude CLI for AI-powered generation
- **gh** - GitHub CLI for release management
- **code2prompt** - Codebase to markdown conversion

### Internal Dependencies

```
auto-commit.sh
  └─ generate-commit-message.sh

commit-msg-all.sh
  └─ commit-msg-package.sh

publish-ver.sh
  ├─ sync-ver.sh
  │   └─ load-packages.sh
  └─ copy-resources.sh

update-split-yml.sh
  └─ generate-split-matrix.sh
      └─ load-packages.sh

release-notes-all.sh
  └─ release-notes-summary.sh
      └─ release-notes-diff.sh
```

---

## Common Patterns

### Error Handling
All scripts use `set -e` to exit on first error, ensuring failures don't propagate.

### Color Output
Many scripts use colored output:
- 🟢 GREEN: Info/Success messages
- 🟡 YELLOW: Warnings
- 🔴 RED: Errors

### Package Iteration Pattern
```bash
for dir in packages/*; do
  if [ -f "$dir/composer.json" ]; then
    # Process package
  fi
done
```

### Claude CLI Integration Pattern
```bash
# Create temp file with context
temp_file=$(mktemp)
echo "Context..." > "$temp_file"

# Call Claude CLI
result=$(claude --print "prompt" < "$temp_file" 2>&1)

# Cleanup
rm -f "$temp_file"
```

---

## Workflow Examples

### Complete Release Workflow
```bash
# 1. Generate release notes
./scripts/release-notes-all.sh > docs/release-notes/v2.5.0.mdx

# 2. Edit release notes as needed
# Edit docs/release-notes/v2.5.0.mdx

# 3. Publish release
./scripts/publish-ver.sh 2.5.0

# Result: Version synced, committed, tagged, and GitHub release created
```

### Development Workflow with Commits
```bash
# Make changes to code...

# Generate and commit with AI-generated message
./scripts/auto-commit.sh

# Or manually generate message
message=$(./scripts/generate-commit-message.sh)
git commit -m "$message"
```

### Clean Rebuild Workflow
```bash
# 1. Clean everything
./scripts/clean-composer.sh

# 2. Fresh install
./scripts/composer-install-all.sh

# 3. Run all tests
./scripts/run-all-tests.sh

# 4. Run static analysis
./scripts/psalm-all.sh
```

### Package-Specific Release Notes
```bash
# Generate diff for manual review
./scripts/release-notes-diff.sh instructor

# Generate AI summary
./scripts/release-notes-summary.sh instructor

# Include in larger release notes document
```
