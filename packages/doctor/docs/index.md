---
title: 'Doctor'
description: 'Documentation generation tools for Instructor PHP'
---

Doctor is an internal package that handles documentation generation for the Instructor PHP project. It supports multiple output formats (Mintlify, MkDocs) and provides autodiscovery of package documentation.

## Features

- **Multi-format output** - Generate documentation for both Mintlify and MkDocs from the same sources
- **Package autodiscovery** - Automatically discovers and includes documentation from `packages/*/docs/`
- **Metadata-driven navigation** - Control navigation order via `_meta.yaml` files or front matter
- **Example integration** - Automatically includes cookbook examples with code inlining

## Commands

Generate documentation using composer scripts:

```bash
# Generate Mintlify documentation
composer docs gen:mintlify

# Generate MkDocs documentation
composer docs gen:mkdocs

# Generate both
composer docs gen:all
```

## Documentation Structure

```
docs/                    # Main documentation (manually curated)
├── index.md
├── features.md
├── getting-started.md
└── ...

packages/*/docs/         # Package documentation (autodiscovered)
├── _meta.yaml          # Navigation ordering
├── index.md
├── quickstart.md
└── essentials/
    ├── _meta.yaml
    └── ...

examples/                # Cookbook examples
└── ...
```

## Topics

- [Navigation Ordering](navigation-ordering.md) - Control documentation structure with metadata
- [Package Discovery](package-discovery.md) - How packages are autodiscovered
