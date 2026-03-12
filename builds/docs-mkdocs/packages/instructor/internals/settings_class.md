---
title: 'Settings Class'
description: 'There is no single global settings object in this package.'
---

## Overview

The structured-output package deliberately avoids a single global settings object.
Configuration is split across purpose-specific classes, keeping request
configuration local and shared behavior reusable.


## Configuration Classes

### `LLMConfig`

Holds provider connection settings: API URL, API key, model name, driver, token
limits, and provider-specific options.

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;

// From a named preset
$config = LLMConfig::fromPreset('openai');

// From explicit values
$config = new LLMConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: 'sk-...',
    model: 'gpt-4o',
    driver: 'openai-compatible',
    maxTokens: 4096,
);

// From an array (e.g., loaded from a config file)
$config = LLMConfig::fromArray($data);

// From a DSN string
$config = LLMConfig::fromDsn('driver=openai,model=gpt-4o,apiKey=sk-...');
// @doctest id="1c56"
```

### `StructuredOutputConfig`

Controls the structured-output behavior: output mode, retry settings, prompt
templates, schema naming, and response caching.

```php
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;

$config = new StructuredOutputConfig(
    outputMode: OutputMode::Tools,
    maxRetries: 2,
    toolName: 'extract_data',
    toolDescription: 'Extract structured data from the input.',
);
// @doctest id="966e"
```

Key settings:

| Setting | Default | Purpose |
|---|---|---|
| `outputMode` | `Tools` | How the schema is delivered to the LLM (`Tools`, `Json`, `JsonSchema`, `MdJson`, `Text`, `Unrestricted`) |
| `maxRetries` | `0` | Maximum retry attempts after the first call |
| `retryPrompt` | `"JSON generated incorrectly..."` | Template for retry feedback messages |
| `toolName` | `"extracted_data"` | Function name used in tool-call mode |
| `toolDescription` | `"Function call based on..."` | Description sent with the tool definition |
| `schemaName` | `"default_schema"` | Name used in JSON Schema mode |
| `useObjectReferences` | `false` | Enable `$ref` usage in generated schemas |
| `defaultToStdClass` | `false` | Return `stdClass` instead of arrays for untyped schemas |
| `responseCachePolicy` | `None` | Whether to cache streaming response snapshots for replay |

### `StructuredOutputRuntime`

Assembles the runtime by combining an inference provider, event dispatcher,
structured-output config, and optional pipeline customizations:

```php
use Cognesy\Instructor\StructuredOutputRuntime;

$runtime = StructuredOutputRuntime::fromConfig($llmConfig);

// Or with full customization
$runtime = new StructuredOutputRuntime(
    inference: $inferenceRuntime,
    events: $eventDispatcher,
    config: $structuredConfig,
    validators: [MyValidator::class],
    transformers: [MyTransformer::class],
);
// @doctest id="170c"
```


## Why No Global Settings?

Splitting configuration serves several goals:

1. **Locality** -- each request can use a different provider or different retry
   settings without affecting other requests.
2. **Testability** -- configuration objects are plain value objects that can be
   constructed in tests without touching global state.
3. **Composability** -- the same `LLMConfig` can be shared across the
   structured-output package, the Polyglot inference layer, and other companions
   without coupling them together.
4. **Immutability** -- both `LLMConfig` and `StructuredOutputConfig` are immutable.
   Mutation methods return new instances, making configuration safe to share across
   concurrent or reentrant code paths.
