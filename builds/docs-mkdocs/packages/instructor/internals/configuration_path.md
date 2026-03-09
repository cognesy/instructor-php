---
title: Configuration Path
description: 'How explicit config resolution fits into this package.'
---

This package does not depend on a published configuration path.

If your application resolves presets from files, that happens before `StructuredOutput` sees the configuration. At the package boundary, the relevant types are:

- `LLMConfig`
- `StructuredOutputConfig`
- `StructuredOutputRuntime`

For preset-based resolution, use `LLMConfig::fromPreset(...)`.
