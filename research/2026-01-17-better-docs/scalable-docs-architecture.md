# Scalable Documentation Architecture

## Problem Statement

1. **Scale constraint**: 24 packages, only 4 have docs - Mintlify tabs don't scale to 20+
2. **Manual wiring**: Package list hardcoded in `MintlifyDocumentation.php:68`
3. **Static navigation**: `mint.json` (316 lines) manually maintained
4. **No autodiscovery**: Adding package docs requires code changes

## Solution: Category-Based Autodiscovery

### Information Architecture

Instead of 1 tab per package, group into **functional categories**:

```
Instructor (Primary Tab)
├── Getting Started, Essentials, Advanced, Internals

LLM Infrastructure (Tab)
├── Polyglot
├── HTTP Client
├── Messages
└── Stream

Data & Schema (Tab)
├── Schema
├── Dynamic
├── Templates
└── Config

Extensions (Tab)
├── Laravel
├── Events
├── Logging
├── Metrics
└── Pipeline

Tools (Tab)
├── Evals
├── Doctor
├── Hub
└── Addons

Cookbook (Dynamic Tab)
Changelog (Dynamic Tab)
```

### Front Matter Schema

Each doc file specifies its placement:

```yaml
---
title: 'Working with Streaming Responses'
package: 'polyglot'          # Required: matches config
section: 'essentials'        # Required: section within package
weight: 10                   # Optional: sort order (lower = first)
---
```

### Configuration File: `/config/docs.yaml`

Single source of truth for documentation structure:

```yaml
# Categories define Mintlify tabs and grouping
categories:
  instructor:
    name: 'Instructor'
    is_primary: true
    packages: [instructor]

  llm-infrastructure:
    name: 'LLM Infrastructure'
    url: 'llm'
    packages: [polyglot, http-client, messages, stream]

  data-schema:
    name: 'Data & Schema'
    url: 'data'
    packages: [schema, dynamic, templates, config]

  extensions:
    name: 'Extensions'
    url: 'ext'
    packages: [laravel, events, logging, metrics, pipeline]

  tools:
    name: 'Development Tools'
    url: 'tools'
    packages: [evals, doctor, hub, addons]

# Package definitions
packages:
  instructor:
    name: 'Instructor'
    docs_dir: 'packages/instructor/docs'
    menu_prefix: 'instructor'
    sections:
      getting-started: { name: 'Getting Started', weight: 1 }
      essentials: { name: 'Essentials', weight: 20 }
      advanced: { name: 'Advanced', weight: 40 }
      internals: { name: 'Internals', weight: 60 }

  polyglot:
    name: 'Polyglot'
    docs_dir: 'packages/polyglot/docs'
    menu_prefix: 'polyglot'
    sections:
      getting-started: { name: 'Getting Started', weight: 1 }
      essentials: { name: 'Essentials', weight: 20 }
      # ... more sections

  # Add packages as they get documentation...

# Output configuration
output:
  mintlify:
    target_dir: 'docs-build'
    file_extension: 'mdx'
  mkdocs:
    target_dir: 'docs-mkdocs'
    file_extension: 'md'
```

### Discovery Flow

```
1. Load /config/docs.yaml
2. For each package in config:
   ├── Scan docs_dir for .md/.mdx files
   ├── Parse front matter
   └── Validate package/section
3. Build navigation per output format
4. Write mint.json / mkdocs.yml
```

### Key Benefits

| Before | After |
|--------|-------|
| Hardcoded 4-package list | Config-driven, any packages |
| 316-line manual mint.json | Auto-generated navigation |
| Code change to add package | Add to config + create docs |
| 1 tab per package (doesn't scale) | Categories group packages |

## Implementation

### New Files

```
/config/docs.yaml                                    # Central config
/packages/doctor/src/Docgen/Config/DocsConfig.php    # Config parser
/packages/doctor/src/Docgen/Discovery/DocumentationDiscovery.php
/packages/doctor/src/Docgen/Builder/MintlifyNavigationBuilder.php
/packages/doctor/src/Docgen/Builder/MkDocsNavigationBuilder.php
```

### Modified Files

```
/packages/doctor/src/Docgen/MintlifyDocumentation.php  # Use discovery
/packages/doctor/src/Docgen/MkDocsDocumentation.php    # Use discovery
```

### Front Matter Updates

Existing docs need `package` and `section` fields added:
- `/packages/instructor/docs/` (~30 files)
- `/packages/polyglot/docs/` (~35 files)
- `/packages/http-client/docs/` (~12 files)
- `/packages/laravel/docs/` (~11 files)

Total: ~88 files need front matter updates

## Adding a New Package's Docs

**Step 1**: Add to `/config/docs.yaml`:
```yaml
packages:
  my-new-package:
    name: 'My Package'
    docs_dir: 'packages/my-new-package/docs'
    menu_prefix: 'my-package'
    sections:
      getting-started: { name: 'Getting Started', weight: 1 }
```

**Step 2**: Add to a category:
```yaml
categories:
  extensions:
    packages: [..., my-new-package]
```

**Step 3**: Create docs with front matter:
```markdown
---
title: 'Overview'
package: 'my-new-package'
section: 'getting-started'
weight: 1
---

# My Package Overview
...
```

**Step 4**: Run `composer docs gen:mintlify` - navigation auto-generated!

## Validation Command

```bash
composer docs validate
```

Checks:
- Front matter has required fields
- `package` matches config
- `section` exists in package config
- No duplicate weights in same section
