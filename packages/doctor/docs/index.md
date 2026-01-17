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
- **LLM-optimized output** - Generate `llms.txt` and `llms-full.txt` for AI consumption

## Commands

Generate documentation using composer scripts:

```bash
# Generate Mintlify documentation
composer docs gen:mintlify

# Generate MkDocs documentation
composer docs gen:mkdocs

# Generate MkDocs + LLM docs together
composer docs gen:mkdocs --with-llms

# Generate LLM-friendly documentation
composer docs gen:llms

# Generate and deploy LLM docs to website
composer docs gen:llms --deploy
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

- [LLM Documentation](llm-docs.md) - Generate LLM-friendly documentation (llms.txt)
- [Navigation Ordering](navigation-ordering.md) - Control documentation structure with metadata
- [Package Discovery](package-discovery.md) - How packages are autodiscovered
