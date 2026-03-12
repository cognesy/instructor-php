---
title: Setup
description: 'Install Polyglot and configure it for your PHP project.'
meta:
  - name: 'has_code'
    content: true
---

This guide walks you through installing Polyglot, configuring API keys, and
choosing the right configuration strategy for your project.


## Installation

Install Polyglot via Composer:

```bash
composer require cognesy/instructor-polyglot
```

> **Note:** Polyglot ships as part of the Instructor PHP monorepo. If you
> already have `cognesy/instructor-php` installed, Polyglot is included
> automatically -- there is no need to install it separately.

### Requirements

- PHP 8.3 or higher
- Composer
- A valid API key for at least one supported LLM provider


## Setting Up API Keys

Polyglot authenticates with LLM providers through API keys. The simplest
approach is to export them as environment variables:

```bash
export OPENAI_API_KEY=sk-your-openai-key
export ANTHROPIC_API_KEY=sk-ant-your-anthropic-key
export GEMINI_API_KEY=your-gemini-key
```

If your project uses a `.env` file, add the keys there instead and load
them with a library such as `vlucas/phpdotenv`. Polyglot's bundled preset
files reference these variables using `${VAR_NAME}` syntax, so the names
above are the expected defaults.


## Quick Start

Once an API key is available, a single call is all you need:

```php
<?php
require 'vendor/autoload.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$text = Inference::using('openai')
    ->withMessages(Messages::fromString('Say hello.'))
    ->get();
```

If you see a friendly greeting, your installation is working correctly.

The `using()` method loads a **preset** -- a small YAML file that tells
Polyglot which driver, API URL, endpoint, and model to use. Polyglot ships
with presets for every supported provider, so you can swap `'openai'` for
`'anthropic'`, `'gemini'`, `'mistral'`, or any other supported name and it
will just work (provided the matching API key is set).


## Configuration Strategies

Polyglot offers three ways to configure connections, from simplest to most
flexible.

### 1. Bundled Presets (Zero Configuration)

Polyglot ships with ready-made presets for all supported providers. Set the
appropriate environment variable and call `using()`:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

// LLM inference
$text = Inference::using('anthropic')
    ->withMessages(Messages::fromString('Explain photosynthesis in one sentence.'))
    ->get();
```

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

// Embeddings
$result = Embeddings::using('openai')
    ->withInputs('The quick brown fox.')
    ->create();
```

Bundled presets live inside the package at `resources/config/llm/presets/`
and `resources/config/embed/presets/`. You never need to edit these files --
override them with your own presets instead (see below).

### 2. Custom Presets (App-Owned YAML Files)

When you need to change a model, adjust token limits, or add metadata,
create your own preset files. Polyglot checks the following directories
in order and uses the first match:

| Priority | Path |
|----------|------|
| 1 | `config/llm/presets/` (or `config/embed/presets/`) |
| 2 | `packages/polyglot/resources/config/llm/presets/` |
| 3 | `vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/presets/` |
| 4 | `vendor/cognesy/instructor-polyglot/resources/config/llm/presets/` |

All paths are relative to your project root. A file placed in
`config/llm/presets/` takes precedence over the bundled default.

#### LLM Preset Example

Create `config/llm/presets/openai.yaml`:

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1-nano
maxTokens: 1024
contextLength: 1000000
maxOutputLength: 16384
```

The `driver` field determines which Polyglot driver handles the request.
The `apiKey` value supports `${ENV_VAR}` interpolation so secrets never
appear in plain text.

#### Embeddings Preset Example

Create `config/embed/presets/openai.yaml`:

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /embeddings
model: text-embedding-3-small
dimensions: 1536
maxInputs: 2048
```

Once the file exists, `Inference::using('openai')` or
`Embeddings::using('openai')` will resolve it automatically.

You can also create entirely new presets for custom deployments. For example,
to add a preset for a local vLLM server, create `config/llm/presets/local-vllm.yaml`:

```yaml
driver: openai-compatible
apiUrl: 'http://localhost:8000/v1'
apiKey: 'not-needed'
endpoint: /chat/completions
model: meta-llama/Llama-3-8b
maxTokens: 2048
```

Then use it like any other preset:

```php
$text = Inference::using('local-vllm')
    ->withMessages(Messages::fromString('Say hello.'))
    ->get();
```

#### EmbeddingsConfig Reference

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `driver` | string | `'openai'` | Driver identifier |
| `apiUrl` | string | `''` | Base URL of the provider API |
| `apiKey` | string | `''` | Authentication key |
| `endpoint` | string | `''` | API endpoint path (e.g., `/embeddings`) |
| `model` | string | `''` | Embedding model identifier |
| `dimensions` | int | `0` | Output vector dimensions |
| `maxInputs` | int | `0` | Maximum number of inputs per request |
| `metadata` | array | `[]` | Provider-specific metadata |

### 3. Runtime Configuration (Programmatic)

When connection details come from a database, user input, or any other
dynamic source, build the config object directly in PHP:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::fromConfig(new LLMConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4.1-nano',
    maxTokens: 2048,
));

$text = $inference
    ->withMessages(Messages::fromString('What is the capital of France?'))
    ->get();
```

The same approach works for embeddings:

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

#### LLMConfig Reference

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `driver` | string | `'openai-compatible'` | Driver identifier (see supported drivers below) |
| `apiUrl` | string | `''` | Base URL of the provider API |
| `apiKey` | string | `''` | Authentication key |
| `endpoint` | string | `''` | API endpoint path |
| `model` | string | `''` | Model identifier |
| `maxTokens` | int | `1024` | Maximum tokens in the response |
| `contextLength` | int | `8000` | Context window size |
| `maxOutputLength` | int | `4096` | Maximum output length |
| `queryParams` | array | `[]` | Additional query parameters |
| `metadata` | array | `[]` | Provider-specific metadata |
| `options` | array | `[]` | Additional driver options |
| `pricing` | array | `[]` | Token pricing configuration (per 1M tokens) |


#### Overriding a Preset at Runtime

You can start from a preset and selectively override specific values using
`withOverrides()`:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$config = LLMConfig::fromPreset('openai')
    ->withOverrides([
        'model' => 'gpt-4.1',
        'maxTokens' => 4096,
    ]);

$inference = Inference::fromConfig($config);
```

This is useful when you want to keep all the defaults from a preset but
need to swap the model or adjust limits for a specific use case.


## DSN Strings

For compact, inline configuration you can use a DSN (Data Source Name)
string. This is useful when storing connection info in a single environment
variable or database column:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$config = LLMConfig::fromDsn(
    'driver=openai,apiUrl=https://api.openai.com/v1,endpoint=/chat/completions,model=gpt-4.1-nano'
);

$inference = Inference::fromConfig($config);
```

DSN strings are comma-separated `key=value` pairs. Nested keys use dot
notation (e.g., `metadata.apiVersion=2023-06-01`).


## Supported Providers

Polyglot includes built-in drivers for the following providers:

| Driver | Provider |
|--------|----------|
| `openai` | OpenAI |
| `anthropic` | Anthropic |
| `gemini` | Google Gemini (native API) |
| `gemini-oai` | Google Gemini (OpenAI-compatible) |
| `azure` | Azure OpenAI |
| `mistral` | Mistral AI |
| `cohere` | Cohere |
| `groq` | Groq |
| `deepseek` | DeepSeek |
| `fireworks` | Fireworks AI |
| `openrouter` | OpenRouter |
| `together` | Together AI |
| `ollama` | Ollama (local) |
| `perplexity` | Perplexity |
| `cerebras` | Cerebras |
| `sambanova` | SambaNova |
| `xai` | xAI (Grok) |
| `a21` | AI21 Labs |
| `meta` | Meta Llama API |
| `minimaxi` | MiniMaxi |
| `inception` | Inception |
| `huggingface` | Hugging Face |
| `qwen` | Qwen |
| `glm` | GLM |
| `bedrock-openai` | AWS Bedrock (OpenAI-compatible) |
| `openai-responses` | OpenAI Responses API |
| `openai-compatible` | Any OpenAI-compatible API |

Any provider that exposes an OpenAI-compatible chat completions endpoint
can be used with the `openai-compatible` driver by pointing `apiUrl` to the
provider's base URL.


## Troubleshooting

**Composer dependency errors.** Verify that you are running PHP 8.3 or
higher (`php -v`) and that Composer is up to date (`composer self-update`).

**"No preset directory found" exception.** This means Polyglot could not
locate any preset YAML file for the name you passed to `using()`. Double-check
the preset name and ensure your custom config directory (if any) follows
the expected structure: `config/llm/presets/<name>.yaml`.

**API key not found.** Make sure the environment variable is exported in the
same shell session that runs your PHP script. You can verify with
`echo $OPENAI_API_KEY` before launching PHP.

**Wrong model or endpoint.** Create a custom preset (see above) to override
the bundled defaults with the model and endpoint you need.
