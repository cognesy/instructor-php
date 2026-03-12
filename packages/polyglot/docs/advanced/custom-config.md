---
title: Custom Configuration
description: Build config objects when presets are not enough.
---

Presets are the recommended way to configure Polyglot. They live in YAML files, keep secrets in
environment variables, and let you switch providers without touching application code. However,
there are situations where presets are not sufficient -- when configuration values are dynamic,
generated at runtime, or sourced from your own application's settings. In these cases, you can
build `LLMConfig` or `EmbeddingsConfig` objects directly.


## The Configuration Files

Polyglot ships with YAML preset files organized in two directories:

- **`config/llm/presets/`** -- one file per inference provider (e.g. `openai.yaml`, `anthropic.yaml`)
- **`config/embed/presets/`** -- one file per embeddings provider

A typical preset file looks like this:

```yaml
# config/llm/presets/openai.yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
metadata:
  organization: ''
  project: ''
model: gpt-4.1-nano
maxTokens: 1024
contextLength: 1000000
maxOutputLength: 16384
```

Environment variable references like `${OPENAI_API_KEY}` are resolved automatically at load
time. Polyglot searches for preset files in the following directories, in order:

1. `config/llm/presets/` (your project root)
2. `packages/polyglot/resources/config/llm/presets/` (monorepo layout)
3. `vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/presets/`
4. `vendor/cognesy/instructor-polyglot/resources/config/llm/presets/`

The first directory that exists wins. You can override the search path by passing a `$basePath`
argument to `Inference::using()`.


## Configuration Parameters

Each LLM configuration includes these parameters:

| Parameter | Type | Description |
|---|---|---|
| `driver` | `string` | The protocol driver to use (e.g. `openai`, `anthropic`, `openai-compatible`) |
| `apiUrl` | `string` | Base URL for the provider's API |
| `apiKey` | `string` | API key for authentication |
| `endpoint` | `string` | The API endpoint path (e.g. `/chat/completions`) |
| `queryParams` | `array` | Optional query string parameters appended to the URL |
| `metadata` | `array` | Provider-specific settings (organization, API version, etc.) |
| `model` | `string` | Default model name |
| `maxTokens` | `int` | Default maximum tokens for responses |
| `contextLength` | `int` | Maximum context window supported by the model |
| `maxOutputLength` | `int` | Maximum output length supported by the model |
| `options` | `array` | Default request options passed to every call |
| `pricing` | `array` | Optional token pricing information for cost tracking |

Embeddings configurations use a similar structure with `dimensions` and `maxInputs` instead of
the token-related fields.


## Runtime Configuration with LLMConfig

When you need to build a configuration programmatically, use the `LLMConfig` class:

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
    contextLength: 1000000,
    maxOutputLength: 16384,
);

$text = Inference::fromConfig($config)
    ->withMessages(Messages::fromString('Say hello.'))
    ->get();
```

Note: `Messages` is imported from `Cognesy\Messages\Messages`. You can create messages from a
string with `Messages::fromString()` or from an array of role/content pairs with
`Messages::fromArray()`.

This is useful when your application stores provider credentials in a database, rotates API
keys at runtime, or needs to construct configurations for providers not included in the
bundled presets.

### Creating Configurations from Arrays

You can also create an `LLMConfig` from an associative array, which is convenient when loading
configuration from a database or external source:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;

$config = LLMConfig::fromArray([
    'driver' => 'openai',
    'apiUrl' => 'https://api.openai.com/v1',
    'apiKey' => $apiKeyFromDatabase,
    'endpoint' => '/chat/completions',
    'model' => 'gpt-4.1-nano',
    'maxTokens' => 1024,
]);
```

### Overriding Configuration Values

If you need to modify an existing configuration, use `withOverrides()` to create a new
instance with specific values changed:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;

$baseConfig = LLMConfig::fromPreset('openai');

// Create a variant with a different model and higher token limit
$highCapConfig = $baseConfig->withOverrides([
    'model' => 'gpt-4.1',
    'maxTokens' => 4096,
]);
```


## Embeddings Configuration

The embeddings equivalent follows the same pattern:

```php
<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = Embeddings::fromConfig(new EmbeddingsConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/embeddings',
    model: 'text-embedding-3-small',
    dimensions: 1536,
    maxInputs: 2048,
));
```

The embeddings configuration parameters are:

| Parameter | Type | Description |
|---|---|---|
| `driver` | `string` | The driver to use (e.g. `openai`, `cohere`, `gemini`, `jina`) |
| `apiUrl` | `string` | Base URL for the provider's API |
| `apiKey` | `string` | API key for authentication |
| `endpoint` | `string` | The API endpoint path (e.g. `/embeddings`) |
| `model` | `string` | The embedding model name |
| `dimensions` | `int` | The dimensionality of the embedding vectors |
| `maxInputs` | `int` | Maximum number of inputs per batch request |
| `metadata` | `array` | Provider-specific settings |


## DSN Input

Both `LLMConfig` and `EmbeddingsConfig` support a lightweight DSN (Data Source Name) format
for quick inline configuration:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$config = LLMConfig::fromDsn('openai://api.openai.com/v1?model=gpt-4.1-nano&apiKey=sk-...');

$response = Inference::fromConfig($config)
    ->withMessages(Messages::fromString('Hello!'))
    ->get();
```

The DSN format encodes the driver as the scheme, the host and path as the API URL, and
query parameters for the remaining configuration values.


## Managing API Keys

API keys should never be committed to your codebase. Polyglot preset files use environment
variable references (`${OPENAI_API_KEY}`) that are resolved at load time. Store your keys in
a `.env` file:

```bash
OPENAI_API_KEY=sk-your-key-here
ANTHROPIC_API_KEY=sk-ant-your-key-here
GEMINI_API_KEY=your-key-here
MISTRAL_API_KEY=your-key-here
```

Load them with a package like `vlucas/phpdotenv`, or rely on your framework's built-in
environment handling (Laravel loads `.env` automatically).

> **Security Tip:** The `apiKey` parameter on `LLMConfig` is marked with PHP's
> `#[SensitiveParameter]` attribute, which prevents it from appearing in stack traces.
> Polyglot also redacts sensitive values from event payloads and debug output.


## Provider-Specific Options

Different providers support unique request parameters. You can pass these through the
`options` parameter on each request:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

// OpenAI-specific options
$response = Inference::using('openai')
    ->with(
        messages: Messages::fromString('Generate a creative story.'),
        options: [
            'temperature' => 0.8,
            'top_p' => 0.95,
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0.5,
            'stop' => ["\n\n", "THE END"],
        ],
    )
    ->get();
```

For Anthropic, the available options differ:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

// Anthropic-specific options
$response = Inference::using('anthropic')
    ->with(
        messages: Messages::fromString('Generate a creative story.'),
        options: [
            'temperature' => 0.7,
            'top_p' => 0.9,
            'top_k' => 40,
        ],
    )
    ->get();
```

Polyglot passes these options through to the provider's API without modification, so consult
each provider's documentation for the full list of supported parameters.

You can also set default options in the preset YAML file so they apply to every request made
with that preset:

```yaml
# config/llm/presets/creative-openai.yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1
maxTokens: 2048
options:
  temperature: 0.9
  top_p: 0.95
```


## Environment-Based Configuration

You can create custom presets for different deployment environments. For example, use a local
Ollama instance in development and a cloud provider in production:

```yaml
# config/llm/presets/dev-local.yaml
driver: openai-compatible
apiUrl: 'http://localhost:11434/v1'
apiKey: ''
endpoint: /chat/completions
model: llama3
maxTokens: 1024
```

Then select the preset based on your application's environment:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$preset = getenv('APP_ENV') === 'production' ? 'openai' : 'dev-local';

$response = Inference::using($preset)
    ->withMessages(Messages::fromString('Hello!'))
    ->get();
```

This pattern keeps your application code completely environment-agnostic. The only thing that
changes between environments is which preset name is selected.


## Creating Custom Preset Files

To add a new provider or a custom configuration, create a new YAML file in your project's
`config/llm/presets/` directory:

```yaml
# config/llm/presets/my-proxy.yaml
driver: openai-compatible
apiUrl: 'https://my-proxy.example.com/v1'
apiKey: '${MY_PROXY_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1-nano
maxTokens: 2048
contextLength: 128000
maxOutputLength: 16384
```

Then reference it by name:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('my-proxy')
    ->withMessages(Messages::fromString('Hello from my proxy!'))
    ->get();
```

Polyglot will find your custom preset file before falling back to the bundled presets, so you
can override any built-in preset by creating a file with the same name in your project's
configuration directory.
