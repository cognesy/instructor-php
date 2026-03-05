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

$responseText = Inference::fromLLMConfig(LLMConfig::fromArray(['driver' => 'openai']))
    ->withModel('gpt-4o-mini')
    ->withMessages('Say hello in one sentence.')
    ->get();
```

Get parsed JSON:

```php
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$data = Inference::fromLLMConfig(LLMConfig::fromArray(['driver' => 'openai']))
    ->withModel('gpt-4o-mini')
    ->withOutputMode(OutputMode::Json)
    ->withMessages('Return {"ok": true}')
    ->asJsonData();
```

## Inference Constructors

```php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$inference = new Inference();
$inference = Inference::fromLLMConfig(LLMConfig::fromArray(['driver' => 'openai']));
$inference = Inference::fromDsn('driver=openai,model=gpt-4o-mini');
$inference = Inference::fromRuntime(InferenceRuntime::fromConfig(LLMConfig::fromArray(['driver' => 'openai'])));
$inference = $inference->withRuntime(InferenceRuntime::fromDsn('driver=openai,model=gpt-4o-mini'));
```

## Inference Request Builder Methods

```php
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
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
    ->withOutputMode(OutputMode::Tools)
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
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$inference = (new Inference)->with(
    messages: $messages,
    model: 'gpt-4o-mini',
    tools: $tools,
    toolChoice: 'auto',
    responseFormat: $responseFormat,
    options: ['temperature' => 0],
    mode: OutputMode::Tools,
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
$stream = $inference->stream();

$isStreamed = $pending->isStreamed();
$text = $pending->get();
$response = $pending->response();
$json = $pending->asJson();
$data = $pending->asJsonData();
$stream = $pending->stream();
```

## Streaming (`InferenceStream`)

```php
$stream = $inference
    ->withStreaming(true)
    ->create()
    ->stream();

foreach ($stream->responses() as $partial) {
    // PartialInferenceResponse
}

$mapped = $stream->map(fn($partial) => $partial->contentDelta);
$filtered = $stream->filter(fn($partial) => $partial->contentDelta !== '');
$total = $stream->reduce(fn($carry, $partial) => $carry + strlen($partial->contentDelta), 0);

$allPartials = $stream->all();
$final = $stream->final(); // ?InferenceResponse

$stream->onPartialResponse(function ($partial): void {
    // callback for each partial
});

$execution = $stream->execution();
```

## Inference Runtime / Provider Setup

```php
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$runtime = InferenceRuntime::fromConfig(LLMConfig::fromArray(['driver' => 'openai']));
$runtime = InferenceRuntime::fromDsn('driver=openai,model=gpt-4o-mini');
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

$vectors = Embeddings::fromEmbeddingsConfig(EmbeddingsConfig::fromArray(['driver' => 'openai']))
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
$embeddings = Embeddings::fromEmbeddingsConfig(EmbeddingsConfig::fromArray(['driver' => 'openai']));
$embeddings = Embeddings::fromDsn('driver=openai,model=text-embedding-3-small');
$embeddings = Embeddings::fromRuntime(EmbeddingsRuntime::fromEmbeddingsConfig(EmbeddingsConfig::fromArray(['driver' => 'openai'])));

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

$runtime = EmbeddingsRuntime::fromEmbeddingsConfig(EmbeddingsConfig::fromArray(['driver' => 'openai']));
$runtime = EmbeddingsRuntime::fromDsn('driver=openai,model=text-embedding-3-small');
$runtime = EmbeddingsRuntime::fromProvider(EmbeddingsProvider::fromEmbeddingsConfig(EmbeddingsConfig::fromArray(['driver' => 'openai'])));

$provider = EmbeddingsProvider::new()
    ->withConfig(EmbeddingsConfig::fromArray(['driver' => 'openai']))
    ->withDsn('driver=openai,model=text-embedding-3-small');
```

Driver registry helper:

```php
Embeddings::registerDriver('custom', $driverFactory);
```

## Useful Enums

```php
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

OutputMode::Unrestricted;
OutputMode::Tools;
OutputMode::Json;
OutputMode::JsonSchema;
OutputMode::MdJson;
OutputMode::Text;

ResponseCachePolicy::None;
ResponseCachePolicy::Memory;
```
