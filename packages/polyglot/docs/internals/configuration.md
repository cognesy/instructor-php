---
title: Configuration
description: The config objects used to build runtimes and presets.
---

Polyglot resolves two configuration types -- one for inference and one for embeddings. Both follow the same patterns: they can be loaded from YAML presets, constructed from arrays, or parsed from DSN strings.


## LLMConfig

`LLMConfig` holds all the settings needed to connect to an inference provider and select a model.

**Namespace:** `Cognesy\Polyglot\Inference\Config\LLMConfig`

### Fields

| Field | Type | Default | Description |
|---|---|---|---|
| `apiUrl` | `string` | `''` | Base URL for the provider API |
| `apiKey` | `string` | `''` | Authentication key (marked as `#[SensitiveParameter]`) |
| `endpoint` | `string` | `''` | API endpoint path (e.g. `/chat/completions`) |
| `queryParams` | `array` | `[]` | Query parameters appended to the URL |
| `metadata` | `array` | `[]` | Provider-specific metadata (e.g. organization, project for OpenAI) |
| `model` | `string` | `''` | Model identifier |
| `maxTokens` | `int` | `1024` | Default max tokens for responses |
| `contextLength` | `int` | `8000` | Model context window size |
| `maxOutputLength` | `int` | `4096` | Maximum output length |
| `driver` | `string` | `'openai-compatible'` | Driver name (e.g. `openai`, `anthropic`, `gemini`) |
| `options` | `array` | `[]` | Additional provider-specific options |
| `pricing` | `array` | `[]` | Token pricing per 1M tokens (input, output, etc.) |

### Creating a Config

There are three ways to create an `LLMConfig`:

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;

// From a named preset (loads from YAML files)
$config = LLMConfig::fromPreset('openai');

// From an associative array
$config = LLMConfig::fromArray([
    'driver' => 'openai',
    'apiUrl' => 'https://api.openai.com/v1',
    'apiKey' => getenv('OPENAI_API_KEY'),
    'endpoint' => '/chat/completions',
    'model' => 'gpt-4.1-nano',
    'maxTokens' => 2048,
]);

// From a DSN string
$config = LLMConfig::fromDsn('openai://model=gpt-4.1-nano&maxTokens=2048');
```

### Presets

Presets are YAML files that live in well-known directories. Polyglot searches these paths in order:

1. `config/llm/presets/` (project root)
2. `packages/polyglot/resources/config/llm/presets/` (monorepo)
3. `vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/presets/`
4. `vendor/cognesy/instructor-polyglot/resources/config/llm/presets/`

You may also pass a custom base path:

```php
$config = LLMConfig::fromPreset('my-preset', basePath: '/path/to/presets');
```

### Overriding Values

Use `withOverrides()` to create a modified copy of an existing config:

```php
$base = LLMConfig::fromPreset('openai');
$custom = $base->withOverrides(['model' => 'gpt-4.1', 'maxTokens' => 4096]);
```

### Pricing

When pricing data is included in the config, it can be used with a cost calculator to compute costs externally. Pricing values are specified in USD per 1 million tokens:

```php
use Cognesy\Polyglot\Inference\Data\InferencePricing;
use Cognesy\Polyglot\Pricing\FlatRateCostCalculator;

$config = LLMConfig::fromArray([
    'driver' => 'openai',
    'apiUrl' => 'https://api.openai.com/v1',
    'apiKey' => getenv('OPENAI_API_KEY'),
    'endpoint' => '/chat/completions',
    'model' => 'gpt-4.1-nano',
    'pricing' => [
        'inputPerMToken' => 0.10,
        'outputPerMToken' => 0.40,
        'cacheReadPerMToken' => 0.0,
        'cacheWritePerMToken' => 0.0,
        'reasoningPerMToken' => 0.0,
    ],
]);

// Cost is calculated externally using a calculator
$pricing = InferencePricing::fromArray($config->pricing);
$calculator = new FlatRateCostCalculator();
$cost = $calculator->calculate($usage, $pricing);
```

### Type Coercion

Both config classes automatically coerce numeric string values to integers for fields that expect `int` types. This is useful when loading values from YAML files or environment variables where values may arrive as strings. For `LLMConfig`, the coerced fields are `maxTokens`, `contextLength`, and `maxOutputLength`.


## EmbeddingsConfig

`EmbeddingsConfig` holds the settings for connecting to an embeddings provider.

**Namespace:** `Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig`

### Fields

| Field | Type | Default | Description |
|---|---|---|---|
| `apiUrl` | `string` | `''` | Base URL for the provider API |
| `apiKey` | `string` | `''` | Authentication key |
| `endpoint` | `string` | `''` | API endpoint path |
| `model` | `string` | `''` | Model identifier |
| `dimensions` | `int` | `0` | Embedding dimensions (0 = provider default) |
| `maxInputs` | `int` | `0` | Maximum number of inputs per request |
| `metadata` | `array` | `[]` | Provider-specific metadata |
| `driver` | `string` | `'openai'` | Driver name |

### Creating a Config

```php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;

// From a named preset
$config = EmbeddingsConfig::fromPreset('openai');

// From an array
$config = EmbeddingsConfig::fromArray([
    'driver' => 'openai',
    'apiUrl' => 'https://api.openai.com/v1',
    'apiKey' => getenv('OPENAI_API_KEY'),
    'endpoint' => '/embeddings',
    'model' => 'text-embedding-3-small',
    'dimensions' => 1536,
]);

// From a DSN string
$config = EmbeddingsConfig::fromDsn('openai://model=text-embedding-3-small');
```

Presets for embeddings are resolved from similar paths, under the `embed` config group:

1. `config/embed/presets/`
2. `packages/polyglot/resources/config/embed/presets/`
3. `vendor/cognesy/instructor-php/packages/polyglot/resources/config/embed/presets/`
4. `vendor/cognesy/instructor-polyglot/resources/config/embed/presets/`

### Overriding Values

```php
$modified = $config->withOverrides([
    'model' => 'text-embedding-3-large',
    'dimensions' => 1024,
]);
```

For `EmbeddingsConfig`, type coercion applies to the `dimensions` and `maxInputs` fields.

> **Note:** The legacy field name `defaultDimensions` is automatically normalized to `dimensions` during config loading.


## Retry Policies

Retry behavior is configured separately from the provider config, via dedicated policy objects. Retry policies must not be placed inside the `options` array -- Polyglot will throw an `InvalidArgumentException` if you attempt this.

### InferenceRetryPolicy

The `InferenceRetryPolicy` provides fine-grained control over retry behavior for inference requests:

```php
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;

$policy = new InferenceRetryPolicy(
    maxAttempts: 3,              // Total attempts (including the first)
    baseDelayMs: 250,            // Base delay between retries
    maxDelayMs: 8000,            // Maximum delay cap
    jitter: 'full',              // Jitter strategy: 'none', 'full', or 'equal'
    retryOnStatus: [408, 429, 500, 502, 503, 504],
    lengthRecovery: 'continue',  // 'none', 'continue', or 'increase_max_tokens'
    lengthMaxAttempts: 1,        // Max recovery attempts for length issues
    lengthContinuePrompt: 'Continue.',
    maxTokensIncrement: 512,     // Increment when using 'increase_max_tokens'
);

$inference->withRetryPolicy($policy);
```

The retry delay uses exponential backoff: `baseDelayMs * 2^(attempt-1)`, capped at `maxDelayMs`. The `jitter` strategy adds randomness to avoid thundering herd problems:

- `'none'` -- exact exponential backoff
- `'full'` -- random value between 0 and the calculated delay
- `'equal'` -- half the delay plus a random amount up to half the delay

Length recovery allows automatic continuation when a response is cut short by the provider's token limit. Two strategies are available: `'continue'` appends the partial response and sends a continuation prompt, while `'increase_max_tokens'` retries with a higher `max_tokens` value.

The policy also retries on specific exceptions by default: `TimeoutException` and `NetworkException`. Provider-specific errors classified as retriable (rate limits, quota exceeded, transient errors) are also retried automatically.

### EmbeddingsRetryPolicy

For embeddings, use `EmbeddingsRetryPolicy`:

```php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;

$embeddings->withRetryPolicy(new EmbeddingsRetryPolicy(
    maxAttempts: 3,
));
```
