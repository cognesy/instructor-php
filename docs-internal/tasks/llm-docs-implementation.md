# LLM-Facing Documentation Implementation

## Overview

Add capability to `composer docs` to generate LLM-friendly documentation files (`llms.txt`, `llms-full.txt`) and deploy them to the instructor-www website.

---

## Task 1: Create LlmsDocsGenerator Service

**Goal:** Create a service class that generates `llms.txt` and `llms-full.txt` from the MkDocs navigation structure.

### Subtasks

1.1. Create `LlmsDocsGenerator` class in `packages/doctor/src/Docgen/`
1.2. Implement `generateIndex()` method - renders navigation as markdown links
1.3. Implement `generateFull()` method - concatenates all MD files into one
1.4. Implement `getDescription()` helper - extracts first paragraph from MD file for link descriptions

### Outcome

```
packages/doctor/src/Docgen/LlmsDocsGenerator.php
```

```php
class LlmsDocsGenerator
{
    public function generateIndex(array $nav, string $outputPath): GenerationResult;
    public function generateFull(string $sourceDir, string $outputPath): GenerationResult;
}
```

### Tests

```bash
# Unit test: generates valid llms.txt structure
vendor/bin/pest --filter=LlmsDocsGeneratorTest::test_generates_valid_index

# Unit test: concatenates files in correct order
vendor/bin/pest --filter=LlmsDocsGeneratorTest::test_generates_full_content

# Unit test: extracts descriptions from markdown
vendor/bin/pest --filter=LlmsDocsGeneratorTest::test_extracts_descriptions
```

### Verification

- [ ] `llms.txt` contains H1 header with project name
- [ ] `llms.txt` contains blockquote description
- [ ] `llms.txt` contains H2 sections for Main, Packages, Cookbook
- [ ] `llms.txt` contains markdown links `[Title](path.md)`
- [ ] `llms-full.txt` contains all documentation content
- [ ] `llms-full.txt` has clear section separators between files

---

## Task 2: Create GenerateLlmsCommand

**Goal:** Create a CLI command `gen:llms` that triggers LLM docs generation.

### Subtasks

2.1. Create `GenerateLlmsCommand` class in `packages/doctor/src/Docgen/Commands/`
2.2. Inject dependencies: `NavigationBuilder`, `LlmsDocsGenerator`, config
2.3. Implement `execute()` - orchestrates generation
2.4. Add `--deploy` option for website deployment
2.5. Add `--target` option for custom deployment path
2.6. Register command in `Docs.php`

### Outcome

```
packages/doctor/src/Docgen/Commands/GenerateLlmsCommand.php
```

```bash
# Available commands
composer docs gen:llms
composer docs gen:llms --deploy
composer docs gen:llms --deploy --target=/custom/path
```

### Tests

```bash
# Feature test: command generates files
vendor/bin/pest --filter=GenerateLlmsCommandTest::test_generates_llms_files

# Feature test: command with deploy option
vendor/bin/pest --filter=GenerateLlmsCommandTest::test_deploys_to_target
```

### Verification

- [ ] `composer docs` lists `gen:llms` command
- [ ] `composer docs gen:llms` creates `docs-mkdocs/llms.txt`
- [ ] `composer docs gen:llms` creates `docs-mkdocs/llms-full.txt`
- [ ] Command outputs success message with file paths
- [ ] Command outputs file sizes

---

## Task 3: Implement llms.txt Index Generation

**Goal:** Generate a well-structured index file following the llms.txt standard.

### Subtasks

3.1. Create header template (project name, description)
3.2. Implement recursive nav-to-markdown renderer
3.3. Add optional description extraction from target files
3.4. Handle nested navigation groups (Packages > Instructor > Essentials)
3.5. Exclude release-notes from index (or make configurable)

### Outcome

```markdown
# Instructor for PHP

> Structured data extraction in PHP, powered by LLMs. Define a PHP class, get a validated object back.

## Main
- [Overview](index.md): Landing page and quick introduction
- [Getting Started](getting-started.md): Installation and first example
- [Features](features.md): Complete feature list
- [Why Instructor](why-instructor.md): Benefits and comparisons

## Packages
- [Overview](packages/index.md): Package architecture and selection guide

### Instructor
- [Introduction](packages/instructor/introduction.md): What is Instructor
- [Quickstart](packages/instructor/quickstart.md): 5-minute getting started
- [Setup](packages/instructor/setup.md): Configuration and API keys

#### Essentials
- [Usage](packages/instructor/essentials/usage.md): Basic usage patterns
- [Data Model](packages/instructor/essentials/data_model.md): Defining response classes
...

## Cookbook
- [Introduction](cookbook/introduction.md): How to use examples

### Basics
- [Simple Extraction](cookbook/instructor/basics/simple.md): Extract structured data
...
```

### Tests

```bash
# Unit test: renders flat navigation
vendor/bin/pest --filter=LlmsIndexTest::test_renders_flat_nav

# Unit test: renders nested navigation with correct heading levels
vendor/bin/pest --filter=LlmsIndexTest::test_renders_nested_nav

# Unit test: extracts description from first paragraph
vendor/bin/pest --filter=LlmsIndexTest::test_extracts_description
```

### Verification

- [ ] Output is valid Markdown
- [ ] All links are relative paths ending in `.md`
- [ ] Nested groups use appropriate heading levels (##, ###, ####)
- [ ] No broken links (all referenced files exist)
- [ ] File size is reasonable (< 50KB)

---

## Task 4: Implement llms-full.txt Concatenation

**Goal:** Concatenate all documentation into a single file for LLM context.

### Subtasks

4.1. Define file ordering (follow navigation order)
4.2. Strip YAML frontmatter from each file
4.3. Add file path header before each section
4.4. Add separator between files
4.5. Handle relative image paths (convert or note)
4.6. Calculate and report total token estimate

### Outcome

```markdown
================================================================================
FILE: index.md
================================================================================

# Instructor for PHP

Structured data extraction in PHP, powered by LLMs...

================================================================================
FILE: getting-started.md
================================================================================

# Getting Started

## Installation

```bash
composer require cognesy/instructor-php
```
...
```

### Tests

```bash
# Unit test: strips frontmatter
vendor/bin/pest --filter=LlmsFullTest::test_strips_frontmatter

# Unit test: adds file separators
vendor/bin/pest --filter=LlmsFullTest::test_adds_separators

# Unit test: follows navigation order
vendor/bin/pest --filter=LlmsFullTest::test_follows_nav_order
```

### Verification

- [ ] All MD files from docs-mkdocs are included
- [ ] No YAML frontmatter in output
- [ ] Clear separators between files
- [ ] File paths in headers match actual paths
- [ ] Total size is reasonable (< 500KB for context limits)

---

## Task 5: Add Deployment Capability

**Goal:** Deploy generated files to instructor-www website.

### Subtasks

5.1. Add `DeploymentConfig` with target paths
5.2. Implement `deployToWebsite()` method
5.3. Copy `llms.txt` to website public root
5.4. Copy `llms-full.txt` to website public root
5.5. Optionally copy full docs folder to `public/docs/`
5.6. Add verification that target exists

### Outcome

```
instructor-www/public/
├── llms.txt          # https://instructorphp.com/llms.txt
├── llms-full.txt     # https://instructorphp.com/llms-full.txt
└── docs/             # https://instructorphp.com/docs/
    ├── index.md
    ├── packages/
    └── cookbook/
```

### Tests

```bash
# Feature test: deploys to target directory
vendor/bin/pest --filter=DeploymentTest::test_deploys_files

# Feature test: fails gracefully if target missing
vendor/bin/pest --filter=DeploymentTest::test_fails_if_target_missing

# Feature test: overwrites existing files
vendor/bin/pest --filter=DeploymentTest::test_overwrites_existing
```

### Verification

- [ ] Files copied to correct location
- [ ] File permissions preserved
- [ ] Symlinks handled correctly
- [ ] Error message if target doesn't exist
- [ ] Success message shows deployed file count

---

## Task 6: Add Configuration

**Goal:** Make paths and options configurable.

### Subtasks

6.1. Add llms config section to `config/docs.yaml`
6.2. Define default website target path
6.3. Add option to include/exclude sections (release-notes, cookbook)
6.4. Add option for description extraction
6.5. Update `DocsConfig` to load llms settings

### Outcome

```yaml
# config/docs.yaml
llms:
  enabled: true
  index_file: llms.txt
  full_file: llms-full.txt

  # Sections to include in llms.txt index
  include_sections:
    - main
    - packages
    - cookbook

  # Sections to exclude
  exclude_sections:
    - release-notes

  # Extract descriptions from first paragraph
  extract_descriptions: true

  # Deployment targets
  deploy:
    website_root: ../instructor-www/public
    docs_folder: docs
```

### Tests

```bash
# Unit test: loads config correctly
vendor/bin/pest --filter=DocsConfigTest::test_loads_llms_config

# Unit test: respects exclude_sections
vendor/bin/pest --filter=LlmsDocsGeneratorTest::test_excludes_sections
```

### Verification

- [ ] Config file loads without errors
- [ ] Excluded sections don't appear in output
- [ ] Default values work when config missing
- [ ] Invalid config produces helpful error

---

## Task 7: Integration with Existing Pipeline

**Goal:** Integrate LLM docs generation into existing doc generation workflow.

### Subtasks

7.1. Add `gen:llms` call to `gen:mkdocs` (optional flag)
7.2. Add `gen:llms` call to `gen:all` command
7.3. Update command help text
7.4. Add progress output during generation

### Outcome

```bash
# Generate everything including LLM docs
composer docs gen:all

# Generate MkDocs + LLM docs
composer docs gen:mkdocs --with-llms

# Generate only LLM docs
composer docs gen:llms

# Generate and deploy
composer docs gen:llms --deploy
```

### Tests

```bash
# Integration test: gen:all includes llms
vendor/bin/pest --filter=IntegrationTest::test_gen_all_includes_llms

# Integration test: gen:mkdocs with flag
vendor/bin/pest --filter=IntegrationTest::test_gen_mkdocs_with_llms_flag
```

### Verification

- [ ] `gen:all` generates llms files
- [ ] `gen:mkdocs --with-llms` generates llms files
- [ ] Timing output includes llms generation time
- [ ] No errors in full pipeline

---

## Task 8: Add View/Output Formatting

**Goal:** Create consistent CLI output for LLM docs generation.

### Subtasks

8.1. Create `LlmsGenerationView` class
8.2. Show progress during file processing
8.3. Show file sizes after generation
8.4. Show token estimate for llms-full.txt
8.5. Show deployment status

### Outcome

```
Generating LLM documentation...

  Scanning navigation structure... done
  Generating llms.txt... done (12.3 KB)
  Generating llms-full.txt... done (287.5 KB, ~72k tokens)

  Deploying to website...
    → ../instructor-www/public/llms.txt
    → ../instructor-www/public/llms-full.txt
    → ../instructor-www/public/docs/ (143 files)

Done in 0.45s
```

### Tests

```bash
# Unit test: view formats sizes correctly
vendor/bin/pest --filter=LlmsGenerationViewTest::test_formats_file_sizes

# Unit test: view shows token estimate
vendor/bin/pest --filter=LlmsGenerationViewTest::test_shows_token_estimate
```

### Verification

- [ ] Output is readable and well-formatted
- [ ] File sizes shown in human-readable format
- [ ] Token estimate is reasonable approximation
- [ ] Errors displayed in red
- [ ] Success displayed in green

---

## Task 9: Write Tests

**Goal:** Comprehensive test coverage for LLM docs generation.

### Subtasks

9.1. Create test fixtures (sample nav structure, sample MD files)
9.2. Write unit tests for `LlmsDocsGenerator`
9.3. Write unit tests for index rendering
9.4. Write unit tests for full content concatenation
9.5. Write feature tests for command execution
9.6. Write integration tests for full pipeline

### Outcome

```
packages/doctor/tests/
├── Unit/
│   └── Docgen/
│       ├── LlmsDocsGeneratorTest.php
│       ├── LlmsIndexRendererTest.php
│       └── LlmsFullRendererTest.php
├── Feature/
│   └── Docgen/
│       └── GenerateLlmsCommandTest.php
└── Integration/
    └── LlmsPipelineTest.php
```

### Verification

- [ ] All tests pass
- [ ] Code coverage > 80% for new code
- [ ] Edge cases covered (empty nav, missing files, etc.)

---

## Task 10: Documentation

**Goal:** Document the new LLM docs generation feature.

### Subtasks

10.1. Add section to Doctor package README
10.2. Document config options
10.3. Document CLI commands
10.4. Add example output

### Outcome

```markdown
## LLM Documentation Generation

Generate documentation optimized for LLM consumption:

### Commands

- `gen:llms` - Generate llms.txt and llms-full.txt
- `gen:llms --deploy` - Generate and deploy to website

### Configuration

See `config/docs.yaml` for options...
```

### Verification

- [ ] README updated
- [ ] Config options documented
- [ ] Example commands shown
- [ ] Example output shown

---

## Summary

| Task | Effort | Dependencies |
|------|--------|--------------|
| 1. LlmsDocsGenerator Service | Medium | None |
| 2. GenerateLlmsCommand | Small | Task 1 |
| 3. llms.txt Index Generation | Medium | Task 1 |
| 4. llms-full.txt Concatenation | Medium | Task 1 |
| 5. Deployment Capability | Small | Task 2 |
| 6. Configuration | Small | Task 1 |
| 7. Pipeline Integration | Small | Tasks 2, 5 |
| 8. View/Output Formatting | Small | Task 2 |
| 9. Tests | Medium | All tasks |
| 10. Documentation | Small | All tasks |

**Recommended order:** 1 → 3 → 4 → 2 → 6 → 5 → 7 → 8 → 9 → 10

---

## Acceptance Criteria

The feature is complete when:

1. [ ] `composer docs gen:llms` generates valid `llms.txt`
2. [ ] `composer docs gen:llms` generates valid `llms-full.txt`
3. [ ] `composer docs gen:llms --deploy` copies files to instructor-www
4. [ ] `https://instructorphp.com/llms.txt` is accessible
5. [ ] `https://instructorphp.com/llms-full.txt` is accessible
6. [ ] All tests pass
7. [ ] Documentation is complete
