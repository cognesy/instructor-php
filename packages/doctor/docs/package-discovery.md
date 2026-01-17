---
title: 'Package Discovery'
description: 'How documentation is autodiscovered from packages'
---

The documentation system automatically discovers and includes documentation from packages in the `packages/` directory.

## How It Works

1. **Scan** - The system scans `packages/` for subdirectories
2. **Check** - Each package is checked for a `docs/` subdirectory
3. **Include** - Packages with `docs/` are included in the generated documentation
4. **Build** - Navigation is built from the directory structure

## Directory Structure

For a package to be autodiscovered, it must have documentation in `packages/<name>/docs/`:

```
packages/
├── instructor/
│   ├── src/
│   ├── docs/           # ✓ Will be discovered
│   │   ├── index.md
│   │   └── ...
│   └── composer.json
├── polyglot/
│   ├── src/
│   ├── docs/           # ✓ Will be discovered
│   │   └── ...
│   └── composer.json
└── some-package/
    ├── src/
    └── composer.json   # ✗ No docs/ - not included
```

## Package Metadata

### Description

Package descriptions come from (in order):

1. `config/docs.yaml` - Manual override
2. `composer.json` - The `description` field

### Title

Package titles are derived from the package name:
- `instructor` → "Instructor"
- `http-client` → "HTTP Client"
- `polyglot` → "Polyglot"

## Configuration

### Package Order

Control the order packages appear in navigation via `config/docs.yaml`:

```yaml
packages:
  order:
    - instructor
    - polyglot
    - http-client
    - laravel
```

Packages not in the list appear after listed ones, alphabetically.

### Custom Descriptions

Override package descriptions:

```yaml
packages:
  descriptions:
    instructor: 'Structured output extraction from LLMs'
    polyglot: 'Unified LLM API client'
```

### Target Directories

Map package names to different output directories:

```yaml
packages:
  target_dirs:
    http-client: http
```

This would output `http-client` docs to `packages/http/` instead of `packages/http-client/`.

## Adding a New Package

To add documentation for a new package:

1. **Create docs directory**:
   ```bash
   mkdir -p packages/my-package/docs
   ```

2. **Add index page**:
   ```bash
   cat > packages/my-package/docs/index.md << 'EOF'
   ---
   title: 'My Package'
   description: 'What my package does'
   ---

   Introduction to my package...
   EOF
   ```

3. **Add navigation ordering** (optional):
   ```bash
   cat > packages/my-package/docs/_meta.yaml << 'EOF'
   order:
     - index
     - quickstart
     - usage
   EOF
   ```

4. **Regenerate documentation**:
   ```bash
   composer docs gen:mintlify
   composer docs gen:mkdocs
   ```

The package will automatically appear in the Packages section of the documentation.

## Output Structure

Generated documentation follows this structure:

```
docs-build/                    # Mintlify output
├── packages/
│   ├── index.mdx             # Packages listing
│   ├── instructor/
│   │   ├── introduction.mdx
│   │   └── ...
│   └── polyglot/
│       └── ...

docs/                          # MkDocs output
├── packages/
│   ├── index.md
│   ├── instructor/
│   └── polyglot/
```

## Packages Listing Page

A packages index page is automatically generated at `packages/index.md(x)`. This page:

- Lists all discovered packages
- Shows package descriptions
- Links to each package's documentation

You can customize this page by creating `docs/packages.md` in the project root. If this file exists, it will be used as the source (with package list appended).
