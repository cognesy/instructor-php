---
title: Configuration Issues
description: Diagnose and resolve preset loading and configuration problems.
---

Configuration issues typically surface when Polyglot cannot find a preset file, when the file is missing required fields, or when field values have the wrong type. These problems usually produce clear error messages that point directly to the cause.

## Symptoms

- `InvalidArgumentException` with "No preset directory found" or "Invalid configuration"
- Unexpected driver or model being used
- Type errors mentioning `maxTokens`, `dimensions`, or `maxInputs`

## Preset File Location

When you call `Inference::using('openai')`, Polyglot searches for a file named `openai.yaml` in these directories (in order):

1. `config/llm/presets/` -- relative to your project root
2. `packages/polyglot/resources/config/llm/presets/` -- monorepo layout
3. `vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/presets/` -- installed via Composer as part of instructor-php
4. `vendor/cognesy/instructor-polyglot/resources/config/llm/presets/` -- installed via Composer as standalone package

For embeddings, the equivalent paths use `config/embed/presets/` instead of `config/llm/presets/`.

If none of these directories exist, Polyglot throws an `InvalidArgumentException`. To override the search path, pass a `basePath` argument:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::using('my-custom-preset', basePath: '/app/config/llm');
```

## Required Preset Fields

A minimal LLM preset YAML file must include:

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1-nano
maxTokens: 1024
contextLength: 128000
maxOutputLength: 16384
```

The following fields are required or strongly recommended:

| Field | Type | Description |
|---|---|---|
| `driver` | string | The driver name (e.g. `openai`, `anthropic`, `gemini`, `ollama`) |
| `apiUrl` | string | Base URL of the provider API |
| `apiKey` | string | API key, usually referencing an env var via `${VAR_NAME}` |
| `endpoint` | string | API endpoint path (e.g. `/chat/completions`, `/messages`) |
| `model` | string | Default model identifier |
| `maxTokens` | integer | Maximum tokens for the response |
| `contextLength` | integer | Maximum context window size |
| `maxOutputLength` | integer | Maximum output length in tokens |

Optional fields include `metadata` (an associative array for provider-specific values like `organization` or `apiVersion`), `queryParams`, `options`, and `pricing`.

## Integer Field Validation

The fields `maxTokens`, `contextLength`, and `maxOutputLength` must be valid integers. If these values are provided as strings in YAML (e.g. `"1024"` instead of `1024`), Polyglot coerces them automatically. However, non-numeric strings or floats will cause an `InvalidArgumentException`.

For embeddings presets, the same rule applies to `dimensions` and `maxInputs`.

## Building Configuration Programmatically

If your configuration is dynamic -- for example, when the user selects a model at runtime -- prefer building `LLMConfig` directly instead of relying on preset files:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$config = new LLMConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4.1-nano',
    maxTokens: 2048,
);

$text = Inference::fromConfig($config)
    ->withMessages(Messages::fromString('Hello'))
    ->get();
```

You can also create a config from an associative array:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;

$config = LLMConfig::fromArray([
    'driver' => 'anthropic',
    'apiUrl' => 'https://api.anthropic.com/v1',
    'apiKey' => getenv('ANTHROPIC_API_KEY'),
    'endpoint' => '/messages',
    'model' => 'claude-haiku-4-5-20251001',
    'maxTokens' => 1024,
]);
```

## Overriding Preset Values

To start from a preset and change specific values, use `withOverrides()`:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$config = LLMConfig::fromPreset('openai')
    ->withOverrides(['model' => 'gpt-4.1', 'maxTokens' => 4096]);

$text = Inference::fromConfig($config)
    ->withMessages(Messages::fromString('Hello'))
    ->get();
```

## Verify a Configuration

To check that a preset loads correctly without making a request, instantiate the config and inspect it:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;

try {
    $config = LLMConfig::fromPreset('openai');
    echo "Driver: {$config->driver}\n";
    echo "API URL: {$config->apiUrl}\n";
    echo "Model: {$config->model}\n";
    echo "Max Tokens: {$config->maxTokens}\n";
} catch (\InvalidArgumentException $e) {
    echo "Configuration error: " . $e->getMessage() . "\n";
}
```

## Common Pitfalls

- **Preset name does not match the filename.** `Inference::using('gpt4')` looks for `gpt4.yaml`, not `openai.yaml`.
- **YAML indentation errors.** Malformed YAML will cause the config loader to fail silently or return unexpected values.
- **Retry policy in options.** Polyglot explicitly forbids placing `retryPolicy` inside the `options` array of `LLMConfig`. Use `withRetryPolicy()` on the inference builder instead.
- **Environment variable not expanded.** If the `apiKey` field contains the literal string `${OPENAI_API_KEY}` at runtime, the environment variable was not resolved. Ensure the variable is set before the preset is loaded.
