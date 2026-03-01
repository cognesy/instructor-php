---
title: Configuration Deep Dive
description: How to define presets and build runtime configs safely.
---

Use presets for normal app usage.
Use runtime config objects when you need dynamic overrides.

## Minimal `config/llm.php`

```php
<?php
use Cognesy\Config\Env;

return [
    'defaultPreset' => 'openai',
    'presets' => [
        'openai' => [
            'driver' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'model' => 'gpt-4.1-nano',
            'maxTokens' => 1024,
        ],
    ],
];
```

Required fields for an LLM preset are `driver`, `apiUrl`, `apiKey`, `endpoint`, and `model`.

## Minimal `config/embed.php`

```php
<?php
use Cognesy\Config\Env;

return [
    'defaultPreset' => 'openai',
    'presets' => [
        'openai' => [
            'driver' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/embeddings',
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
            'maxInputs' => 2048,
        ],
    ],
];
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
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::fromDsn('preset=openai,model=gpt-4.1-nano,maxTokens=256')
    ->withMessages('Write a commit message for a bugfix.')
    ->get();
```

## Register and Use a Custom Driver Alias

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use App\LLM\AcmeDriver;

Inference::registerDriver('acme', AcmeDriver::class);

// Use "driver" => "acme" in an LLM preset or LLMConfig.
```

## Environment-Based Default Preset

```php
<?php
use Cognesy\Config\Env;

return [
    'defaultPreset' => Env::get('APP_ENV', 'prod') === 'prod' ? 'openai' : 'ollama',
    'presets' => [/* ... */],
];
```

## See Also

- [Setup](../setup.md)
- [Extending Polyglot](./extending.md)
- [Internals: configuration](../internals/configuration.md)
