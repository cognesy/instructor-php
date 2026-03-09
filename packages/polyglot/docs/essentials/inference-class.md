---
title: Inference Class
description: Public API of the Inference facade.
---

`Inference` is a request facade over `InferenceRuntime`.
Use it to build requests fluently, then execute as text, response object, JSON, or stream.

## Create an Instance

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$default = new Inference();
$openai = Inference::using('openai');
$fromDsnConfig = Inference::fromConfig(LLMConfig::fromDsn('driver=openai,model=gpt-4.1-nano'));
```

## Build a Request

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = (new Inference())
    ->withMessages('Summarize CQRS in two sentences.')
    ->withModel('gpt-4.1-nano')
    ->withOptions(['temperature' => 0.2])
    ->withMaxTokens(120)
    ->withStreaming(false);
```

## Execute

```php
<?php
$text = $inference->get();          // string
$response = $inference->response(); // InferenceResponse
$json = $inference->asJson();       // JSON string
$data = $inference->asJsonData();   // array
$toolJson = $inference->asToolCallJson();     // tool call args as JSON string
$toolData = $inference->asToolCallJsonData(); // tool call args as array
$stream = $inference->stream();     // InferenceStream
```

## One-Call Configuration

```php
<?php
$data = (new Inference())
    ->with(
        messages: 'Return valid JSON with key "status".',
        responseFormat: ['type' => 'json_object'],
        options: ['temperature' => 0],
    )
    ->asJsonData();
```

## Runtime-First Usage

```php
<?php
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromConfig(LLMConfig::fromPreset('openai'));

$pending = $runtime->create(new InferenceRequest(messages: 'Ping', model: 'gpt-4.1-nano'));
$text = $pending->get();
```

## Extend Drivers Per Runtime

```php
<?php
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Events\Contracts\CanHandleEvents;

$drivers = BundledInferenceDrivers::registry()->withDriver(
    'openai-custom',
    function (
        LLMConfig $config,
        HttpClient $httpClient,
        CanHandleEvents $events,
    ): CanProcessInferenceRequest {
        return new OpenAIDriver($config, $httpClient, $events);
    },
);

$runtime = InferenceRuntime::fromConfig(
    config: LLMConfig::fromArray(['driver' => 'openai-custom']),
    drivers: $drivers,
);
```

## See Also

- [Inference overview](./overview.md)
- [Creating requests](./creating-requests.md)
- [Request options](./request-options.md)
- [Extending Polyglot](../advanced/extending.md)
