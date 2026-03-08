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

```php
use Cognesy\Polyglot\Inference\Inference;

$responseText = Inference::fromConfig(LLMConfig::fromArray(['driver' => 'openai']))
    ->withModel('gpt-4o-mini')
    ->withMessages('Say hello in one sentence.')
    ->get();
```

Get parsed JSON:

```php
$data = Inference::fromConfig(LLMConfig::fromArray(['driver' => 'openai']))
    ->withModel('gpt-4o-mini')
    ->withResponseFormat(['type' => 'json_object'])
    ->withMessages('Return {"ok": true}')
    ->asJsonData();
```

## Inference Constructors

```php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$inference = new Inference();
$inference = Inference::fromConfig(LLMConfig::fromArray(['driver' => 'openai']));
$inference = Inference::fromConfig(LLMConfig::fromDsn('driver=openai,model=gpt-4o-mini'));
$inference = Inference::fromRuntime(InferenceRuntime::fromConfig(LLMConfig::fromArray(['driver' => 'openai'])));
$inference = $inference->withRuntime(InferenceRuntime::fromConfig(LLMConfig::fromDsn('driver=openai,model=gpt-4o-mini')));
```

## Inference Request Builder Methods

```php
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

$inference = (new Inference)
    ->withMessages($messages)
    ->withModel('gpt-4o-mini')
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
$inference = (new Inference)->with(
    messages: $messages,
    model: 'gpt-4o-mini',
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
    model: 'gpt-4o-mini',
);

$inference = (new Inference)->withRequest($request);
$pending = $inference->create();
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
$total = $stream->reduce(fn($carry, $delta) => $carry + strlen($delta->contentDelta), 0);

$allDeltas = $stream->all();
$final = $stream->final(); // ?InferenceResponse

$stream->onDelta(function ($delta): void {
    // callback for each delta
});

$execution = $stream->execution();
```

## Inference Runtime / Provider Setup

```php
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$runtime = InferenceRuntime::fromConfig(LLMConfig::fromArray(['driver' => 'openai']));
$runtime = InferenceRuntime::fromConfig(LLMConfig::fromDsn('driver=openai,model=gpt-4o-mini'));
$runtime = InferenceRuntime::fromProvider(LLMProvider::fromLLMConfig(LLMConfig::fromArray(['driver' => 'openai'])));

$provider = LLMProvider::new()
    ->withLLMConfig(LLMConfig::fromArray(['driver' => 'openai']))
    ->withDsn('driver=openai,model=gpt-4o-mini')
    ->withConfigOverrides(['temperature' => 0])
    ->withModel('gpt-4o-mini');
```

Driver registry helpers:

```php
Inference::registerDriver('custom', $driverFactory);
Inference::unregisterDriver('custom');
Inference::resetDrivers();
```

## Embeddings Quick Start

```php
use Cognesy\Polyglot\Embeddings\Embeddings;

$vectors = Embeddings::fromConfig(EmbeddingsConfig::fromArray(['driver' => 'openai']))
    ->withModel('text-embedding-3-small')
    ->withInputs(['hello world'])
    ->vectors();
```

## Embeddings Constructors and Builder Methods

```php
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;

$embeddings = new Embeddings();
$embeddings = Embeddings::fromConfig(EmbeddingsConfig::fromArray(['driver' => 'openai']));
$embeddings = Embeddings::fromConfig(EmbeddingsConfig::fromDsn('driver=openai,model=text-embedding-3-small'));
$embeddings = Embeddings::fromRuntime(EmbeddingsRuntime::fromConfig(EmbeddingsConfig::fromArray(['driver' => 'openai'])));

$embeddings = $embeddings
    ->withInputs(['a', 'b'])
    ->withModel('text-embedding-3-small')
    ->withOptions(['dimensions' => 512])
    ->withRetryPolicy(new EmbeddingsRetryPolicy(maxAttempts: 3));
```

Single-call variant:

```php
$embeddings = (new Embeddings)->with(
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

$pending = (new Embeddings)
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
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

$runtime = EmbeddingsRuntime::fromConfig(EmbeddingsConfig::fromArray(['driver' => 'openai']));
$runtime = EmbeddingsRuntime::fromConfig(EmbeddingsConfig::fromDsn('driver=openai,model=text-embedding-3-small'));
$runtime = EmbeddingsRuntime::fromProvider(EmbeddingsProvider::fromEmbeddingsConfig(EmbeddingsConfig::fromArray(['driver' => 'openai'])));

$provider = EmbeddingsProvider::new()
    ->withConfig(EmbeddingsConfig::fromArray(['driver' => 'openai']))
    ->withDsn('driver=openai,model=text-embedding-3-small');
```

Driver registry helper:

```php
Embeddings::registerDriver('custom', $driverFactory);
```

## Useful Types

```php
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

ResponseFormat::text();
ResponseFormat::jsonObject();
ResponseFormat::jsonSchema(['type' => 'object']);

ResponseCachePolicy::None;
ResponseCachePolicy::Memory;
```
