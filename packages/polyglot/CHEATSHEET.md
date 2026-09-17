---
title: Polyglot
description: Unified LLM API — inference, embeddings, request building, streaming, and provider configuration
package: polyglot
---

<!-- markdownlint-disable MD013 MD025 -->

# Polyglot Package Cheatsheet

Code-verified API reference for `packages/polyglot`.

## Core Facades

```php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Decision\Decision;

$inference = new Inference();
$embeddings = new Embeddings();
$decision = new Decision();
```

## Inference Quick Start

Use a preset for the normal path:

```php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$message = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMessages(Messages::fromString('Say hello in one sentence.'))
    ->get();

$text = $message->content()->toString();
```

Get parsed JSON:

```php
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

$data = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withResponseFormat(ResponseFormat::jsonObject())
    ->withMessages(Messages::fromString('Return JSON with key "ok".'))
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
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

$inference = Inference::using('openai')
    ->withMessages($messages)           // Messages
    ->withModel('gpt-4.1-nano')
    ->withMaxTokens(800)
    ->withTools($tools)                 // ToolDefinitions
    ->withToolChoice(ToolChoice::auto())
    ->withResponseFormat($responseFormat) // ResponseFormat
    ->withOptions(['temperature' => 0])
    ->withStreaming(true)
    ->withResponseCachePolicy(ResponseCachePolicy::Memory)
    ->withRetryPolicy(new InferenceRetryPolicy(maxAttempts: 3))
    ->withCachedContext(
        messages: $cachedMessages,       // ?Messages
        tools: $cachedTools,             // ?ToolDefinitions
        toolChoice: ToolChoice::auto(),
        responseFormat: $cachedResponseFormat, // ?ResponseFormat
    );
```

Single-call variant:

```php
$inference = Inference::using('openai')->with(
    messages: $messages,           // ?Messages
    model: 'gpt-4.1-nano',
    tools: $tools,                 // ?ToolDefinitions
    toolChoice: ToolChoice::auto(),
    responseFormat: $responseFormat, // ?ResponseFormat
    options: ['temperature' => 0],
);
```

With explicit request:

```php
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

$request = new InferenceRequest(
    messages: Messages::fromString('Hello'),
    model: 'gpt-4.1-nano',
);

$pending = Inference::using('openai')
    ->withRequest($request)
    ->create();
```

## Inference Execution Surfaces

```php
$pending = $inference->create();

$message = $inference->get();
$response = $inference->response();
$json = $inference->asJson();
$data = $inference->asJsonData();
$toolJson = $inference->asToolCallJson();
$toolData = $inference->asToolCallJsonData();
$stream = $inference->stream();

$isStreamed = $pending->isStreamed();
$message = $pending->get();
$response = $pending->response();
$json = $pending->asJson();
$data = $pending->asJsonData();
$toolJson = $pending->asToolCallJson();
$toolData = $pending->asToolCallJsonData();
$stream = $pending->stream();
```

## Reasoning, Model Facts, and Pricing

Reasoning is projected from the ordered assistant message:

```php
$reasoning = $response->message()->reasoningContent();

foreach ($stream->deltas() as $delta) {
    echo $delta->messageChunks->reasoningDelta();
}
```

Read model facts using one exact `(driver, wire model)` key. An absent key
returns unknown facts; the catalog does not infer from the model name:

```php
use Cognesy\Polyglot\Inference\Models\ModelCatalog;

$profile = ModelCatalog::discover()->find('qwen', 'qwen3.8-max');
$profile->limits->contextWindow;
$profile->capabilities->tools;
$profile->capabilities->reasoning;
```

The catalog is not used by ordinary inference unless it is passed explicitly to
`InferenceRuntime`. Reuse one discovered catalog at the application composition
root when local capability policy or metadata is needed. Known semantic fallback
is off by default; direct PHP config uses `allowLossyFallback: true`, while
Laravel and Symfony connection config uses `allow_lossy_fallback: true`. Every
accepted adjustment is observable on the effective request and
`InferenceRequested` event.

Pricing is caller-owned and is not stored in model catalog records:

```php
use Cognesy\Polyglot\Inference\Data\InferencePricing;
use Cognesy\Polyglot\Inference\Pricing\FlatRateCostCalculator;

$cost = (new FlatRateCostCalculator())->calculate(
    usage: $response->usage(),
    // Illustrative USD rates per 1M tokens; supply your current sourced rates.
    pricing: new InferencePricing(
        inputPerMToken: 0.20,
        outputPerMToken: 0.80,
    ),
);
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

$mapped = $stream->map(fn($delta) => $delta->messageChunks->textDelta());
$filtered = $stream->filter(fn($delta) => $delta->messageChunks->textDelta() !== '');
$total = $stream->reduce(
    fn($carry, $delta) => $carry + strlen($delta->messageChunks->textDelta()),
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
use Cognesy\Polyglot\Inference\Creation\InferenceDriverRegistry;

$drivers = InferenceDriverRegistry::default()
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
```

## Decision Quick Start

```php
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

$questions = Questions::of(
    new Noul('billing', 'Is this about billing?'),
    new Choice(
        id: 'tone',
        options: ChoiceOptions::of(
            new ChoiceOption('calm'),
            new ChoiceOption('frustrated'),
        ),
        instructions: 'What is the tone?',
    ),
    new Score(
        id: 'urgency',
        levels: ScoreLevels::of('low', 'medium', 'high'),
        instructions: 'How urgent is this?',
    ),
);

$answers = Decision::using('typesafe')
    ->with(input: 'Charged twice; please help today.', questions: $questions)
    ->get();

$probability = $answers->noul('billing')->probability();
$tone = $answers->choice('tone')->value();
$urgency = $answers->score('urgency')->value();
```

## Decision Runtime and Pending Result

```php
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\DecisionRuntime;

$runtime = DecisionRuntime::fromConfig(DecisionConfig::fromPreset('typesafe'));
$pending = $runtime->create(new DecisionRequest(
    input: 'Charged twice; please help today.',
    questions: $questions,
    retryPolicy: new DecisionRetryPolicy(maxAttempts: 3),
));

$request = $pending->request();
$executionId = $pending->executionId();
$answers = $pending->get();
$response = $pending->response(); // memoized after get()
```

Decision defaults to one attempt and has no streaming API. Lifecycle telemetry uses
`sdm.decision` and `sdm.decision.attempt`. See `docs/decision/overview.md` for
serialization, structured content, dynamic options, retry ownership, and live testing.

## Testing

Deterministic test seams:

- `Tests\Support\FakeInferenceDriver`
  - queue sync `InferenceResponse` fixtures or streaming `PartialInferenceDelta` batches
  - best for most inference runtime tests that do not need HTTP or adapter coverage
- `Tests\Support\FakeEmbeddingsDriver`
  - queue `EmbeddingsResponse` fixtures and record handled requests
  - best for most embeddings runtime and memoization tests
- `MockHttpDriver`
  - use when transport and provider adapter behavior still matter
  - best for golden tests, request assertions, and provider-specific error-path coverage
- `POLYGLOT_TYPESAFE_LIVE=1`
  - opts into the bounded TypeSafe integration smoke
  - ordinary test runs remain offline
