---
title: 'Configuration Path'
description: 'How configuration paths are resolved for provider presets.'
---

## Overview

The structured-output package itself does not depend on a published configuration
path. At the package boundary, configuration is passed as typed objects
(`LLMConfig`, `StructuredOutputConfig`, `StructuredOutputRuntime`).

Configuration path resolution matters when you load LLM provider presets from
YAML files. This page explains how that resolution works.


## Preset-Based Configuration

Load a named preset to create an `LLMConfig`:

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$config = LLMConfig::fromPreset('openai');
// @doctest id="8d8f"
```

You can also pass an explicit base path:

```php
$config = LLMConfig::fromPreset('openai', '/path/to/my/presets');
// @doctest id="c57e"
```


## Path Resolution Order

When no explicit base path is given, `LLMConfig::fromPreset()` searches the
following locations (in order) and uses the first one that exists:

1. `config/llm/presets/` -- project-level published presets
2. `packages/polyglot/resources/config/llm/presets/` -- monorepo location
3. `vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/presets/` -- Composer install (monorepo package)
4. `vendor/cognesy/instructor-polyglot/resources/config/llm/presets/` -- Composer install (standalone package)

This means presets work automatically in most project layouts without any
manual path configuration.


## Environment Variable

You can set the `INSTRUCTOR_CONFIG_PATHS` environment variable in your `.env` file
to tell the broader InstructorPHP ecosystem where to find configuration files:

```ini
INSTRUCTOR_CONFIG_PATHS='config,vendor/cognesy/instructor-php/config'
# @doctest id="2f20"
```

This variable is used by companion packages and the CLI tooling. The
structured-output package does not read it directly -- it relies on `LLMConfig`
preset resolution or explicit configuration objects.


## Key Types at the Package Boundary

| Type | Purpose |
|---|---|
| `LLMConfig` | Provider connection settings (API URL, key, model, driver) |
| `StructuredOutputConfig` | Structured output behavior (output mode, retries, prompts) |
| `StructuredOutputRuntime` | Assembled runtime with inference provider and event handling |

If your application resolves presets from files, that resolution happens before
these types are constructed. The structured-output package only sees the resulting
typed objects.
