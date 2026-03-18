---
title: 'Config Files'
description: 'What configuration files do and do not belong to this package.'
---

## Overview

The `cognesy/instructor-struct` package works without any published configuration
files. All structured-output behavior is controlled through typed configuration
objects in code.

When configuration files are present in a project, they typically serve the
companion Polyglot package (provider presets) rather than the structured-output
package itself.


## Provider Presets

LLM provider connections are configured through YAML preset files managed by the
`cognesy/polyglot` package. Each preset defines the API URL, driver, model, token
limits, and other provider-specific settings:

```
packages/polyglot/resources/config/llm/presets/
    openai.yaml
    anthropic.yaml
    gemini.yaml
    groq.yaml
    ...
// @doctest id="d86c"
```

You load a preset with `LLMConfig::fromPreset()`:

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$config = LLMConfig::fromPreset('openai');
// @doctest id="7822"
```

The preset resolution searches several paths automatically (project `config/`
directory, monorepo paths, and Composer vendor paths), so presets work out of the
box in most setups.


## Configuration Groups

In the broader InstructorPHP ecosystem, configuration is organized into groups.
Each group is stored in a separate file. The main groups are:

| Group | Purpose |
|---|---|
| `llm` | LLM provider connections and presets |
| `structured` | Structured output behavior (output mode, retries, prompts) |
| `embed` | Embedding provider connections |
| `http` | HTTP client configurations |
| `prompt` | Prompt libraries and settings |
| `web` | Web service providers (scrapers, etc.) |
| `debug` | Debugging settings |

The structured-output package only consumes the `structured` and `llm` groups.
Other groups belong to companion packages.


## Structured Output Configuration

Rather than reading from config files, the structured-output package uses the
`StructuredOutputConfig` class directly:

```php
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;

$config = new StructuredOutputConfig(
    outputMode: OutputMode::Tools,
    maxRetries: 2,
    toolName: 'extract_data',
);
// @doctest id="0386"
```

This keeps the package independent from any file-based configuration system while
still allowing integration with one when needed (via `fromArray()` or `fromDsn()`).
