# TASK: Generate Release Notes for Instructor PHP

## Context

**Instructor for PHP** is a library for structured data extraction from Large Language Models (LLMs). It simplifies LLM integration by handling the complexity of extracting structured, validated data from LLM outputs.

**Monorepo Structure**: 18 independent packages under `packages/` directory, each with its own `composer.json`, tests, and documentation. Packages include core functionality (`instructor`, `config`, `events`, `messages`, `utils`, `schema`, `templates`), extended functionality (`addons`, `auxiliary`, `polyglot`, `setup`, `hub`, `tell`, `dynamic`), development tools (`evals`, `experimental`, `doctor`), HTTP client (`http-client`), and pipeline processing (`pipeline`).

## Objective

Generate dense, high-signal release notes for the next version of Instructor PHP based on changes since the last tagged release. These notes will be used in the automated release process.

## Source Analysis Instructions

### 1. Identify Latest Release
```bash
# Get the most recent git tag
git tag --sort=-version:refname | head -1
```

### 2. Generate Diff
```bash
# Get changes since last release, excluding documentation directories
git diff --name-status [LAST_TAG]..HEAD \
  -- ':!docs-site' ':!docs-build' ':!docs-mkdocs' | \
  grep -E '\.(php|json|md|yml|yaml|sh)$'
```

### 3. Analyze Changes
Focus on these key areas:

**Core Library Changes:**
- Changes in `packages/instructor/` - main API and functionality
- Changes in `packages/config/`, `packages/events/`, `packages/messages/`, `packages/utils/`, `packages/schema/`, `packages/templates/` - core dependencies
- Changes in `packages/polyglot/` - LLM abstraction layer
- Changes in `packages/http-client/` - HTTP client functionality

**Extended Features:**
- Changes in `packages/addons/` - additional functionality
- Changes in `packages/pipeline/` - processing pipeline
- Changes in `packages/dynamic/` - dynamic structures

**Development Tools:**
- Changes in `packages/evals/`, `packages/doctor/` - development and testing tools
- Changes in `scripts/` - build and release automation
- Changes in root-level files - monorepo configuration

### 4. Breaking Changes Analysis
Identify potential breaking changes by examining:
- Public API modifications in core packages
- Constructor signature changes
- Interface modifications
- Removed or renamed methods/classes
- Configuration changes

## Output Format

Generate release notes in MDX format following this structure:

```mdx
### [Category Name]
- **[Feature/Fix Name]** - [Brief description of change and impact]
- **[Feature/Fix Name]** - [Brief description]

### Breaking Changes
- **[Change Description]** - Migration instructions if applicable

### Bug Fixes  
- **[Fix Description]** - [Brief description of issue resolved]

### Documentation
- **[Doc Changes]** - [Brief description]

---

**Full Changelog**: [v{PREVIOUS}...v{CURRENT}](https://github.com/cognesy/instructor-php/compare/v{PREVIOUS}...v{CURRENT})
```

## Categories to Use

**Primary Categories:**
- **Core Changes** - Main library functionality
- **Breaking Changes** - API changes requiring user code updates
- **New Features** - Added functionality
- **Bug Fixes** - Resolved issues
- **Performance** - Optimization improvements
- **Documentation** - Doc updates and improvements
- **Development** - Build tools, testing, CI/CD changes

**Package-Specific Categories (if significant changes):**
- **JsonSchema Package** - Schema generation changes
- **Pipeline Package** - Processing pipeline updates
- **HTTP Client** - HTTP functionality changes
- **Polyglot Package** - LLM abstraction changes

## Quality Standards

**Dense, High-Signal Content:**
- Focus on user-impacting changes
- Omit internal refactoring unless it affects performance/behavior
- Use bullet points for scanability
- Bold key terms for emphasis
- Include migration guidance for breaking changes

**Technical Accuracy:**
- Reference actual class/method names
- Specify which packages are affected
- Include version comparison link at bottom

**User Focus:**
- Prioritize changes that affect end users
- Explain impact, not just implementation details
- Group related changes logically

## Release Process Context

These notes will be:
1. Saved as `docs/release-notes/v{VERSION}.mdx`
2. Used by `./scripts/publish-ver.sh` for GitHub release creation
3. Distributed to all 18 individual package repositories via GitHub Actions
4. Published on Packagist for each package

## Previous Release Notes Reference

Check `docs/release-notes/v1.4.1.mdx` and `docs/release-notes/v1.4.0.mdx` for format and tone consistency.

## Commands Available

The following scripts are available for analysis:
- `./scripts/sync-ver.sh` - Version synchronization
- `./scripts/publish-ver.sh` - Release process automation  
- `./scripts/load-packages.sh` - Package configuration loading
- `./scripts/run-all-tests.sh` - Testing across packages

## Expected Output

Create comprehensive but concise release notes that:
1. Highlight the most important changes first
2. Group related changes logically
3. Provide clear migration guidance for breaking changes
4. Focus on user impact rather than implementation details
5. Maintain consistent formatting with previous releases

The final output should be ready for immediate use in the release process without further editing.

# TARGET LOCATION OF NEW RELEASE NOTES

./docs/release-notes/
