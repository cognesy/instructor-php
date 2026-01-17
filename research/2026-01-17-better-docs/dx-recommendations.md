# Developer Experience Recommendations

## Overview

This document provides DX-focused recommendations for the documentation system, applicable to both full modularization and incremental approaches.

## 1. Front Matter Best Practices

### Current Schema
```yaml
---
title: 'Basic use'
docname: 'basic_use'
path: ''
---
```

### Recommended Extended Schema

**If going incremental (recommended)**:
```yaml
---
# REQUIRED (existing)
title: 'Working with Streaming'

# OPTIONAL - override docname (default: snake_case of directory)
docname: 'streaming_responses'

# OPTIONAL - override tab from config
tab: 'polyglot'

# OPTIONAL - override section from directory
section: 'advanced'

# OPTIONAL - sort order within section (default: alphabetical)
weight: 20

# OPTIONAL - short CLI description (max 80 chars)
description: 'Stream partial updates from LLM responses'

# OPTIONAL - tags for future filtering
tags: ['streaming', 'real-time']
---
```

**If going full modularization**:
Add required `package` field plus optional `difficulty`, `requires`, `requires_env`.

## 2. CLI Improvements

### Current Interface
```bash
composer hub run 1                    # By index
composer hub run Basic                # By name (partial match)
composer hub run A01_Basics/Basic     # By path
```

### Recommended Additions

**Package filtering** (any approach):
```bash
composer hub list instructor          # List instructor examples only
composer hub list polyglot            # List polyglot examples only
```

**Namespaced references** (if virtual view implemented):
```bash
composer hub run instructor:Basic     # Package:name syntax
composer hub run polyglot:Inference   # Clear ownership
```

**Tag search** (if tags added to front matter):
```bash
composer hub find --tag=streaming     # Cross-package search
```

### CLI Output Improvements

Current output is functional. Consider adding:
- Package/tab grouping in list view
- Difficulty indicators if front matter includes them
- Description preview if available

Example enhanced output:
```
┌─ instructor ────────────────────────────────────────────────┐
│ basics                                                      │
│   (1) Basic              Basic use                          │
│   (2) Validation         Using Symfony validation           │
│ advanced                                                    │
│   (3) Streaming          Streaming partial updates          │
└─────────────────────────────────────────────────────────────┘
```

## 3. Validation Tooling

### Recommended Validation Command
```bash
composer hub validate                 # Validate all examples
composer hub validate --fix           # Auto-fix common issues
```

### Validation Rules

| Rule | Severity | Description |
|------|----------|-------------|
| `title-required` | error | Title field must exist |
| `title-length` | warning | Title < 60 chars |
| `docname-unique` | error | No duplicates within group |
| `code-executable` | warning | `run.php` has valid PHP syntax |

### Pre-commit Hook
```bash
# .husky/pre-commit
composer hub validate --staged-only --quiet
```

## 4. Contributor Workflow

### Creating New Examples

**Recommended scaffolding command**:
```bash
composer hub new A01_Basics/MyExample
# Creates /examples/A01_Basics/MyExample/run.php with template
```

**Template**:
```php
---
title: 'My Example'
docname: 'my_example'
---

## Overview

[Describe what this example demonstrates]

## Example

<?php
require 'examples/boot.php';

// Your example code here
```

### Development Cycle
```bash
# 1. Create scaffold
composer hub new A02_Advanced/NewFeature

# 2. Edit
$EDITOR examples/A02_Advanced/NewFeature/run.php

# 3. Test
composer hub run NewFeature

# 4. Validate
composer hub validate examples/A02_Advanced/NewFeature

# 5. Commit
git add examples/A02_Advanced/NewFeature
git commit -m "Add NewFeature example"
```

## 5. Documentation Testing

### Local Preview
```bash
# Build and serve docs locally
composer docs gen:mkdocs && mkdocs serve
# Visit http://localhost:8000

# Preview single example (if implemented)
composer hub preview Basic
# Opens browser to local docs with example rendered
```

### Validation Before Push
```bash
# Run all checks
composer hub check

# Includes:
# - Front matter validation
# - PHP syntax check (php -l)
# - Example execution test
# - Doc link validation
```

## 6. Documentation Quality

### Example File Structure

Every example should follow this structure:
```
## Overview
[1-2 sentences about what this demonstrates]

## Example
```php
[Runnable code]
```

## Notes (optional)
[Additional context, caveats, related examples]
```

### Quality Checklist for Contributors

```markdown
- [ ] Title is descriptive and unique
- [ ] Code runs without errors
- [ ] Code includes meaningful output
- [ ] No hardcoded API keys or secrets
- [ ] Uses existing patterns from similar examples
- [ ] Has Overview and Example sections
```

## 7. Search and Discovery

### Current Discovery
- Index-based: `composer hub run 42`
- Name-based: `composer hub run Basic`
- Path-based: `composer hub run A01_Basics/Basic`

### Recommended Additions

**Interactive mode** (nice-to-have):
```bash
composer hub browse
# Opens fuzzy-finder with:
# - Type to search titles
# - Filter by package/group
# - Arrow keys to navigate
# - Enter to run
```

**Full-text search**:
```bash
composer hub search "streaming"       # Search titles and descriptions
composer hub search --code "partial"  # Search in example code
```

## 8. Error Handling

### Current Behavior
Errors displayed inline during execution.

### Recommended Improvements

**Better error context**:
```
ERROR in examples/A02_Advanced/Streaming/run.php

  Line 42: Undefined variable $response

  Hint: Did you mean to use $result instead?

  To debug: composer hub run Streaming --debug
```

**Missing dependencies**:
```
WARNING: This example requires OPENAI_API_KEY

  Set it in your .env file or environment:
  export OPENAI_API_KEY="sk-..."

  See: docs/setup.md#api-keys
```

## Summary

These DX improvements can be implemented incrementally:

| Priority | Feature | Effort |
|----------|---------|--------|
| High | Validation command | 4 hours |
| High | Package list filtering | 2 hours |
| Medium | Scaffolding command | 4 hours |
| Medium | Namespaced references | 4 hours |
| Low | Interactive browser | 8 hours |
| Low | Full-text search | 6 hours |

Start with validation and package filtering - they provide the most value with least effort.
