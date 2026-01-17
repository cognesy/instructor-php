# Current Documentation System Analysis

## Overview

This document analyzes the current `composer docs` mechanism and related tooling for the instructor-php project.

## Entry Points

### `composer docs`
- Executes `/bin/instructor-docs`
- Main class: `Cognesy\Doctor\Docs` in `/packages/doctor/src/Docs.php`
- Symfony Console application with multiple commands

### Key Commands
| Command | Description |
|---------|-------------|
| `gen:mintlify` | Generate Mintlify docs to `/docs-build/` |
| `gen:mkdocs` | Generate MkDocs docs to `/docs-mkdocs/` |
| `--packages-only` | Skip examples |
| `--examples-only` | Skip packages |

## Directory Structure

### Source Locations
```
/docs/                          # Project-level docs & templates
  mint.json                     # Mintlify configuration
  mkdocs.yml.template           # MkDocs template
  cookbook/                     # General docs
  release-notes/                # Version changelogs
  images/                       # Shared images

/packages/{pkg}/docs/           # Package-specific docs
  - instructor/docs/            # 40+ markdown files
  - polyglot/docs/              # LLM provider docs
  - http-client/docs/           # HTTP client docs
  - laravel/docs/               # Laravel integration docs

/examples/                      # All examples (222 total)
  boot.php                      # Shared bootstrap
  A01_Basics/                   # Instructor basics (18 examples)
  A02_Advanced/                 # Instructor advanced (16 examples)
  A03_Troubleshooting/          # Instructor troubleshooting (9 examples)
  A04_APISupport/               # API provider support (23 examples)
  A05_Extras/                   # Additional features (20 examples)
  B01_LLM/                      # LLM basics (7 examples)
  B02_LLMAdvanced/              # LLM advanced (12 examples)
  B03_LLMTroubleshooting/       # LLM troubleshooting (5 examples)
  B04_LLMApiSupport/            # LLM API support (22 examples)
  B05_LLMExtras/                # LLM extras (24 examples)
  C01_ZeroShot/                 # Zero-shot prompting (8 examples)
  C02_FewShot/                  # Few-shot learning (4 examples)
  C03_ThoughtGen/               # Chain of thought (10 examples)
  C04_Ensembling/               # Ensemble methods (10 examples)
  C05_SelfCriticism/            # Self-correction (6 examples)
  C06_Decomposition/            # Task decomposition (6 examples)
  C07_Misc/                     # Miscellaneous (22 examples)
```

### Output Locations
```
/docs-build/                    # Mintlify output
/docs-mkdocs/                   # MkDocs output
/mkdocs.yml                     # Generated MkDocs config (project root)
```

## Example Front Matter

Current schema (minimal):
```yaml
---
title: 'Basic use'
docname: 'basic_use'
path: ''
---
```

## Group-to-Tab Mapping

Hardcoded in `/packages/hub/src/Data/Example.php` (lines 47-65):

```php
$mapping = [
    'A01_Basics' => ['tab' => 'instructor', 'name' => 'basics', 'title' => 'Cookbook \ Instructor \ Basics'],
    'A02_Advanced' => ['tab' => 'instructor', 'name' => 'advanced', 'title' => 'Cookbook \ Instructor \ Advanced'],
    // ... 15 more entries
    'C07_Misc' => ['tab' => 'prompting', 'name' => 'misc', 'title' => 'Cookbook \ Prompting \ Miscellaneous'],
];
```

**Tab assignments:**
- `A*` groups → `instructor` tab
- `B*` groups → `polyglot` tab
- `C*` groups → `prompting` tab

## Documentation Generators

### MintlifyDocumentation.php
- Generates `/docs-build/` output
- Updates `mint.json` with navigation
- Manages tab-based organization

### MkDocsDocumentation.php (719 lines)
- Generates `/docs-mkdocs/` output
- Creates `mkdocs.yml` at project root
- Features:
  - `inlineExternalCodeblocks()` - Embeds code references
  - `fixImagePaths()` - Converts absolute to relative paths
  - `buildNavigationFromStructure()` - Dynamic nav from directory layout

## Hub System

### `composer hub run`
- Entry: `/packages/hub/bin/instructor-hub`
- Main class: `Cognesy\InstructorHub\Hub`
- Discovery: `ExampleRepository` scans `/examples/`

### Commands
```bash
composer hub list               # List all examples
composer hub run 1              # Run by index
composer hub run Basic          # Run by name
composer hub all                # Run all examples
composer hub status             # Show execution status
```

## Known Issues

### Duplication
There are examples in TWO locations:
1. `/examples/` - Primary location (222 examples)
2. `/packages/hub/examples/` - Duplicate copy

This duplication should be resolved before any migration.

## Key Files

| File | Purpose |
|------|---------|
| `/packages/doctor/src/Docs.php` | Main CLI app, service wiring |
| `/packages/doctor/src/Docgen/MintlifyDocumentation.php` | Mintlify generator |
| `/packages/doctor/src/Docgen/MkDocsDocumentation.php` | MkDocs generator |
| `/packages/hub/src/Hub.php` | Example runner CLI |
| `/packages/hub/src/Services/ExampleRepository.php` | Example discovery |
| `/packages/hub/src/Data/Example.php` | Example entity + mapping |
| `/packages/hub/src/Data/ExampleInfo.php` | Front matter parsing |
