---
title: 'Configuration Layer'
description: 'How Polyglot resolves LLM and embeddings configuration.'
---

Polyglot resolves two config objects:

- `LLMConfig` for inference
- `EmbeddingsConfig` for embeddings

## LLMConfig

```php
<?php
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$config = new LLMConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: 'sk-...',
    endpoint: '/chat/completions',
    model: 'gpt-4o-mini',
    maxTokens: 1024,
    contextLength: 128000,
    maxOutputLength: 16384,
    driver: 'openai',
    options: ['temperature' => 0.2],
);
// @doctest id="2036"
```

Key fields:

- `driver`: which inference driver to use (`openai`, `anthropic`, `openai-compatible`, etc.)
- `apiUrl`, `endpoint`, `apiKey`: transport/auth settings
- `model`, `maxTokens`, `contextLength`, `maxOutputLength`
- `metadata`, `queryParams`, `pricing`, `options`

## EmbeddingsConfig

```php
<?php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;

$config = new EmbeddingsConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: 'sk-...',
    endpoint: '/embeddings',
    model: 'text-embedding-3-small',
    dimensions: 1536,
    maxInputs: 2048,
    driver: 'openai',
);
// @doctest id="96ed"
```

Key fields:

- `driver`: embeddings driver (`openai`, `cohere`, `gemini`, etc.)
- `apiUrl`, `endpoint`, `apiKey`
- `model`, `dimensions`, `maxInputs`, `metadata`

## Resolution Flow

```php
<?php
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$provider = LLMProvider::using('openai')
    ->withConfigOverrides(['model' => 'gpt-4o']);

$config = $provider->resolveConfig();          // LLMConfig
$runtime = InferenceRuntime::fromProvider($provider);
// @doctest id="98f0"
```

Embeddings flow is equivalent via `EmbeddingsProvider` and `EmbeddingsRuntime`.

## DSN and Overrides

Both providers support DSN input:

```php
<?php
use Cognesy\Polyglot\Inference\LLMProvider;

$provider = LLMProvider::dsn('driver=openai,model=gpt-4o-mini,maxTokens=512');
$config = $provider->resolveConfig();
// @doctest id="74b1"
```

## Notes

- Use `driver`, not `providerType`.
- HTTP client selection is handled in runtime construction (`InferenceRuntime` / `EmbeddingsRuntime`), not as a config field.
- Retry policy is explicit (`withRetryPolicy(...)`), not embedded in generic `options`.
