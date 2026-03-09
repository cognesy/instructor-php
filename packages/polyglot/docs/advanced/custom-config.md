---
title: Configuration Deep Dive
description: How to define presets and build runtime configs safely.
---

Use presets for normal app usage.
Use runtime config objects when you need dynamic overrides.

## Minimal `config/llm/presets/openai.yaml`

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1-nano
maxTokens: 1024
```

Required fields for an LLM preset are `driver`, `apiUrl`, `apiKey`, `endpoint`, and `model`.

## Minimal `config/embed/presets/openai.yaml`

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /embeddings
model: text-embedding-3-small
dimensions: 1536
maxInputs: 2048
```

## Build Config at Runtime

```php
<?php
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$config = new LLMConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4.1-nano',
    driver: 'openai',
    maxTokens: 512,
);

$text = Inference::fromRuntime(InferenceRuntime::fromConfig($config))
    ->withMessages('Explain blue-green deployment in one paragraph.')
    ->get();
```

## Use DSN for Lightweight Overrides

```php
<?php
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::fromConfig(LLMConfig::fromDsn('driver=openai,model=gpt-4.1-nano,maxTokens=256'))
    ->withMessages('Write a commit message for a bugfix.')
    ->get();
```

## Register and Use a Custom Driver Alias

```php
<?php
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use App\LLM\AcmeDriver;

$drivers = BundledInferenceDrivers::registry()->withDriver('acme', AcmeDriver::class);
$runtime = InferenceRuntime::fromConfig(
    config: LLMConfig::fromArray(['driver' => 'acme']),
    drivers: $drivers,
);

// Use "driver" => "acme" in an LLM preset or LLMConfig.
```

## Environment-Based Preset Selection

Set provider keys in `.env`, then select presets at runtime:

```php
$preset = getenv('APP_ENV') === 'prod' ? 'openai' : 'ollama';
$text = \Cognesy\Polyglot\Inference\Inference::using($preset)
    ->withMessages('hello')
    ->get();
```

## See Also

- [Setup](../setup.md)
- [Extending Polyglot](./extending.md)
- [Internals: configuration](../internals/configuration.md)
