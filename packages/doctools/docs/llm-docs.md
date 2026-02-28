---
title: 'LLM Documentation'
description: 'Generate LLM-friendly documentation files'
---

Doctor can generate documentation optimized for LLM consumption, following the [llms.txt standard](https://llmstxt.org/).

## Overview

Two files are generated:

| File | Purpose | Typical Size |
|------|---------|--------------|
| `llms.txt` | Index with links and descriptions | ~30 KB |
| `llms-full.txt` | Complete docs concatenated | ~1 MB (~300k tokens) |

## Output Directories

Understanding where files are generated is important:

| Command | Output Location | Purpose |
|---------|-----------------|---------|
| `gen:llms` | `docs-mkdocs/` | Local generation |
| `gen:llms --deploy` | `docs-mkdocs/` + website | Local + deploy to website |
| `gen:mkdocs --with-llms` | `docs-mkdocs/` | Generate MkDocs then LLM docs |

### Directory Structure

```
instructor-php/
├── docs-mkdocs/              # MkDocs output (source for LLM docs)
│   ├── llms.txt              # Generated here by gen:llms
│   ├── llms-full.txt         # Generated here by gen:llms
│   ├── index.md
│   ├── packages/
│   └── cookbook/
│
└── ../instructor-www/public/  # Website (deployment target)
    ├── llms.txt              # Deployed here with --deploy
    ├── llms-full.txt         # Deployed here with --deploy
    └── docs/                 # Full docs deployed here
```

## Commands

### Basic Generation

```bash
# Generate LLM docs to docs-mkdocs/
composer docs gen:llms

# Generate only the index file
composer docs gen:llms --index-only

# Generate only the full file
composer docs gen:llms --full-only
```

### With Deployment

```bash
# Generate and deploy to website (configured in config/docs.yaml)
composer docs gen:llms --deploy

# Deploy to custom target
composer docs gen:llms --deploy --target=/path/to/website/public
```

### Combined with MkDocs

```bash
# Generate MkDocs first, then LLM docs (to docs-mkdocs/)
composer docs gen:mkdocs --with-llms
```

**Note:** `--with-llms` does NOT deploy to the website. It only generates files in `docs-mkdocs/`.

## Typical Workflows

### Development: Generate Locally

```bash
# Regenerate MkDocs and LLM docs
composer docs gen:mkdocs --with-llms

# Files are now in docs-mkdocs/
ls docs-mkdocs/llms*.txt
```

### Production: Generate and Deploy

```bash
# Option 1: Two-step process
composer docs gen:mkdocs --with-llms
composer docs gen:llms --deploy

# Option 2: Generate fresh and deploy
composer docs gen:llms --deploy
```

### CI/CD Pipeline

```bash
# In your deployment script:
composer docs gen:mkdocs --with-llms
composer docs gen:llms --deploy --target=/var/www/html/public
```

## Output Files

### llms.txt

A markdown index file with links to all documentation:

```markdown
# Instructor for PHP

> Structured data extraction in PHP, powered by LLMs.

## Main
- [Overview](index.md)
- [Getting Started](getting-started.md)
- [Features](features.md)

## Packages
- [Overview](packages/index.md)

### Instructor
- [Introduction](packages/instructor/introduction.md)
- [Quickstart](packages/instructor/quickstart.md)
...

## Cookbook
- [Introduction](cookbook/introduction.md)
...
```

### llms-full.txt

All documentation concatenated into a single file with clear separators:

```markdown
# Instructor for PHP

> Structured data extraction in PHP, powered by LLMs.

This file contains the complete documentation...

================================================================================
FILE: index.md
================================================================================

# Welcome

This is the home page...

================================================================================
FILE: getting-started.md
================================================================================

# Getting Started
...
```

Features:
- YAML frontmatter is stripped
- Files are ordered according to navigation structure
- Clear separators between files
- Token estimate included in generation output
- Release notes excluded by default (configurable)

## Configuration

Configure LLM docs in `config/docs.yaml`:

```yaml
llms:
  # Enable/disable generation
  enabled: true

  # Output filenames
  index_file: 'llms.txt'
  full_file: 'llms-full.txt'

  # Project description for headers
  project_description: 'Structured data extraction in PHP, powered by LLMs.'

  # Sections to exclude from llms-full.txt (saves tokens)
  exclude_sections:
    - 'release-notes/'

  # Deployment settings
  deploy:
    # Target directory (relative to project root)
    target: '../instructor-www/public'
    # Subfolder for markdown files (llms.txt goes to target root)
    docs_folder: 'docs'
```

### Configuration Options

| Option | Default | Description |
|--------|---------|-------------|
| `enabled` | `true` | Enable/disable LLM docs generation |
| `index_file` | `llms.txt` | Filename for the index |
| `full_file` | `llms-full.txt` | Filename for concatenated docs |
| `project_description` | (see config) | Description in file headers |
| `exclude_sections` | `['release-notes/']` | Patterns to exclude from full file |
| `deploy.target` | `''` | Deployment target directory |
| `deploy.docs_folder` | `docs` | Subfolder for markdown files |

## Deployment Details

When using `--deploy`, files are copied to the website:

```
instructor-www/public/          # deploy.target
├── llms.txt                    # → https://instructorphp.com/llms.txt
├── llms-full.txt               # → https://instructorphp.com/llms-full.txt
└── docs/                       # deploy.docs_folder
    ├── index.md
    ├── getting-started.md
    ├── packages/
    │   ├── instructor/
    │   └── polyglot/
    └── cookbook/
```

The deployment:
1. Copies `llms.txt` and `llms-full.txt` to the target root
2. Copies all markdown files to `docs/` subfolder
3. Preserves directory structure

## API

### LlmsDocsGenerator

```php
use Cognesy\Doctor\Docgen\LlmsDocsGenerator;

$generator = new LlmsDocsGenerator(
    projectName: 'My Project',
    projectDescription: 'Project description for headers',
);

// Generate index file
$result = $generator->generateIndex($navigation, '/path/to/llms.txt');

// Generate full concatenated file
$result = $generator->generateFull(
    $navigation,
    '/path/to/source',
    '/path/to/llms-full.txt',
    excludePatterns: ['release-notes/'],
);
```

### GenerationResult

Both methods return a `GenerationResult` with:

```php
$result->isSuccess();        // bool
$result->filesProcessed;     // int
$result->message;            // string (includes file size and token estimate)
$result->errors;             // array
```

## Command Reference

### gen:llms

```
Usage:
  gen:llms [options]

Options:
  -d, --deploy          Deploy generated files to website
  -t, --target=TARGET   Custom deployment target path (overrides config)
  -i, --index-only      Generate only llms.txt index file
  -f, --full-only       Generate only llms-full.txt file
```

### gen:mkdocs --with-llms

```
Usage:
  gen:mkdocs [options]

Options:
  -p, --packages-only   Generate only package documentation
  -e, --examples-only   Generate only example documentation
  -l, --with-llms       Also generate LLM-friendly documentation
```
