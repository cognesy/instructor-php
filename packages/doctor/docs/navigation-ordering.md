---
title: 'Navigation Ordering'
description: 'Control documentation navigation structure with metadata'
---

The documentation system supports metadata-driven navigation ordering. This allows you to control the exact order of pages and sections without relying on alphabetical sorting.

## Overview

Navigation order is determined by (in priority order):

1. **`_meta.yaml`** files in directories - Explicit ordering
2. **Front matter `sidebarPosition`** - Per-file ordering
3. **Default patterns** - Common documentation conventions

## Using `_meta.yaml`

Create a `_meta.yaml` file in any documentation directory to control the order of files and subdirectories.

### Basic Usage

```yaml
# packages/my-package/docs/_meta.yaml
order:
  - introduction
  - quickstart
  - setup
  - essentials      # directories listed by name
  - advanced
  - internals
  - upgrade
  - cli_tools
```

### Key Points

- List items **without file extensions** (use `quickstart` not `quickstart.md`)
- Directories are listed by name (same as files)
- Items not in the list appear after listed items, sorted by defaults
- The `order` key contains an array of item names

### Example Structure

```
packages/instructor/docs/
├── _meta.yaml              # Controls root ordering
├── introduction.md
├── quickstart.md
├── setup.md
├── essentials/
│   ├── _meta.yaml          # Controls essentials/ ordering
│   ├── usage.md
│   ├── validation.md
│   └── modes.md
└── advanced/
    └── ...
```

Root `_meta.yaml`:
```yaml
order:
  - introduction
  - quickstart
  - setup
  - essentials
  - advanced
```

Essentials `_meta.yaml`:
```yaml
order:
  - usage
  - validation
  - modes
```

## Using Front Matter

For individual file ordering without creating `_meta.yaml`, use front matter:

```yaml
---
title: 'My Page'
sidebarPosition: 5
---

# My Page Content
```

Lower numbers appear first. Files with `sidebarPosition` are sorted before files without it.

### Supported Keys

- `sidebarPosition` (Mintlify style)
- `sidebar_position` (Docusaurus style)

Both are supported for compatibility.

## Default Ordering

When no metadata is provided, the system uses sensible defaults:

### Files (in order)

| Priority | Filenames |
|----------|-----------|
| 0 | `index` |
| 1 | `introduction` |
| 2 | `overview` |
| 3 | `quickstart` |
| 4 | `getting-started` |
| 5 | `setup` |
| 6 | `installation` |
| 7 | `configuration` |
| 8 | `usage` |
| 50 | *(other files - alphabetical)* |
| 100 | `upgrade` |
| 101 | `cli_tools`, `cli-tools` |
| 200 | `contributing` |
| 201 | `changelog` |

### Directories (in order)

| Priority | Directory Names |
|----------|-----------------|
| 1 | `concepts` |
| 2 | `essentials` |
| 3 | `basics` |
| 4 | `getting-started` |
| 10 | `modes` |
| 11 | `streaming` |
| 12 | `embeddings` |
| 20 | `advanced` |
| 21 | `techniques` |
| 30 | `internals` |
| 40 | `troubleshooting` |
| 50 | *(other directories - alphabetical)* |
| 100 | `misc` |
| 101 | `reference` |

## Consistency Across Formats

The same `_meta.yaml` files and front matter work for both Mintlify and MkDocs output. You define the order once, and both documentation sites reflect it.

## Best Practices

1. **Use `_meta.yaml` for sections** - When you have multiple files in a directory, create a `_meta.yaml` to ensure consistent ordering

2. **Start with defaults** - The default ordering handles common patterns well; only add `_meta.yaml` when you need specific ordering

3. **List important items first** - Put introductory content at the top of your order list

4. **Group related content** - Use directories to group related pages, then order directories logically

5. **Keep unlisted items** - You don't need to list every file; unlisted items appear after listed ones

## Troubleshooting

### Order not applied

- Ensure `_meta.yaml` is in the correct directory (same level as the files it orders)
- Check YAML syntax is valid
- Verify item names match filenames exactly (without extension)

### Mixed ordering

If some items are ordered and others aren't:
- Listed items appear first (in list order)
- Unlisted items appear after (sorted by defaults, then alphabetically)

### Regenerate documentation

After changing `_meta.yaml`, regenerate:

```bash
composer docs gen:mintlify
composer docs gen:mkdocs
```
