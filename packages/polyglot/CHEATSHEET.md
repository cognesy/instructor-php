# Polyglot Package Cheatsheet

Code-verified API reference for `packages/polyglot`.

## Core Facades

```php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Embeddings\Embeddings;

$inference = new Inference();
$embeddings = new Embeddings();
```

## Inference Quick Start

Use a preset for the normal path:

```php
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMessages('Say hello in one sentence.')
    ->get();
```

Get parsed JSON:

```php
$data = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withResponseFormat(['type' => 'json_object'])
    ->withMessages('Return JSON with key "ok".')
    ->asJsonData();
```

## Inference Constructors

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$inference = new Inference();
$inference = Inference::using('openai');
$inference = Inference::fromConfig(LLMConfig::fromPreset('openai'));
$inference = Inference::fromProvider(LLMProvider::using('openai'));
$inference = Inference::fromRuntime(
    InferenceRuntime::fromConfig(LLMConfig::fromPreset('openai')),
);
$inference = $inference->withRuntime(
    InferenceRuntime::fromConfig(LLMConfig::fromPreset('openai')),
);
```

## Inference Request Builder Methods

```php
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

$inference = Inference::using('openai')
    ->withMessages($messages)
    ->withModel('gpt-4.1-nano')
    ->withMaxTokens(800)
    ->withTools($tools)
    ->withToolChoice('auto')
    ->withResponseFormat($responseFormat)
    ->withOptions(['temperature' => 0])
    ->withStreaming(true)
    ->withResponseCachePolicy(ResponseCachePolicy::Memory)
    ->withRetryPolicy(new InferenceRetryPolicy(maxAttempts: 3))
    ->withCachedContext(
        messages: $cachedMessages,
        tools: $cachedTools,
        toolChoice: 'auto',
        responseFormat: $cachedResponseFormat,
    );
```

Single-call variant:

```php
$inference = Inference::using('openai')->with(
    messages: $messages,
    model: 'gpt-4.1-nano',
    tools: $tools,
    toolChoice: 'auto',
    responseFormat: $responseFormat,
    options: ['temperature' => 0],
);
```

With explicit request:

```php
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

$request = new InferenceRequest(
    messages: 'Hello',
    model: 'gpt-4.1-nano',
);

$pending = Inference::using('openai')
    ->withRequest($request)
    ->create();
```

## Inference Execution Surfaces

```php
$pending = $inference->create();

$text = $inference->get();
$response = $inference->response();
$json = $inference->asJson();
$data = $inference->asJsonData();
$toolJson = $inference->asToolCallJson();
$toolData = $inference->asToolCallJsonData();
$stream = $inference->stream();

$isStreamed = $pending->isStreamed();
$text = $pending->get();
$response = $pending->response();
$json = $pending->asJson();
$data = $pending->asJsonData();
$toolJson = $pending->asToolCallJson();
$toolData = $pending->asToolCallJsonData();
$stream = $pending->stream();
```

## Streaming (`InferenceStream`)

```php
$stream = $inference
    ->withStreaming(true)
    ->create()
    ->stream();

foreach ($stream->deltas() as $delta) {
    // PartialInferenceDelta
}

$mapped = $stream->map(fn($delta) => $delta->contentDelta);
$filtered = $stream->filter(fn($delta) => $delta->contentDelta !== '');
$total = $stream->reduce(
    fn($carry, $delta) => $carry + strlen($delta->contentDelta),
    0,
);

$allDeltas = $stream->all();
$final = $stream->final(); // ?InferenceResponse

$stream->onDelta(function ($delta): void {
    // callback for each visible delta
});

$lastDelta = $stream->lastDelta();
$usage = $stream->usage();
$execution = $stream->execution();
```

## Inference Runtime / Provider Setup

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$runtime = InferenceRuntime::fromConfig(LLMConfig::fromPreset('openai'));
$runtime = InferenceRuntime::fromProvider(LLMProvider::using('openai'));

$provider = LLMProvider::using('openai')
    ->withConfigOverrides(['model' => 'gpt-4.1-nano'])
    ->withModel('gpt-4.1-mini');
```

Driver registry helpers:

```php
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;

$drivers = BundledInferenceDrivers::registry()
    ->withDriver('custom', $driverFactory);

$runtime = InferenceRuntime::fromConfig(
    LLMConfig::fromArray([
        'driver' => 'custom',
        'apiUrl' => 'https://example.test',
        'endpoint' => '/v1/chat',
        'model' => 'custom-model',
    ]),
    drivers: $drivers,
);
```

## Embeddings Quick Start

```php
use Cognesy\Polyglot\Embeddings\Embeddings;

$vectors = Embeddings::using('openai')
    ->withModel('text-embedding-3-small')
    ->withInputs(['hello world'])
    ->vectors();
```

## Embeddings Constructors and Builder Methods

```php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

$embeddings = new Embeddings();
$embeddings = Embeddings::using('openai');
$embeddings = Embeddings::fromConfig(EmbeddingsConfig::fromPreset('openai'));
$embeddings = Embeddings::fromProvider(
    EmbeddingsProvider::fromEmbeddingsConfig(EmbeddingsConfig::fromPreset('openai')),
);
$embeddings = Embeddings::fromRuntime(
    EmbeddingsRuntime::fromConfig(EmbeddingsConfig::fromPreset('openai')),
);

$embeddings = $embeddings
    ->withInputs(['a', 'b'])
    ->withModel('text-embedding-3-small')
    ->withOptions(['dimensions' => 512])
    ->withRetryPolicy(new EmbeddingsRetryPolicy(maxAttempts: 3));
```

Single-call variant:

```php
$embeddings = Embeddings::using('openai')->with(
    input: ['hello'],
    options: ['dimensions' => 512],
    model: 'text-embedding-3-small',
);
```

With explicit request:

```php
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;

$request = new EmbeddingsRequest(
    input: ['hello'],
    model: 'text-embedding-3-small',
);

$pending = Embeddings::using('openai')
    ->withRequest($request)
    ->create();
```

Execution shortcuts:

```php
$response = $embeddings->get();
$vectors = $embeddings->vectors();
$first = $embeddings->first();

$pending = $embeddings->create();
$request = $pending->request();
$response = $pending->get();
```

## Embeddings Runtime / Provider Setup

```php
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

$runtime = EmbeddingsRuntime::fromConfig(EmbeddingsConfig::fromPreset('openai'));
$runtime = EmbeddingsRuntime::fromProvider(
    EmbeddingsProvider::fromEmbeddingsConfig(EmbeddingsConfig::fromPreset('openai')),
);

$provider = EmbeddingsProvider::new()
    ->withConfig(EmbeddingsConfig::fromPreset('openai'))
    ->withConfigOverrides(['model' => 'text-embedding-3-small']);

Embeddings::registerDriver('custom', $driverFactory);
```
