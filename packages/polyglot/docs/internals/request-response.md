---
title: Request and Response Objects
description: 'Core request/response types used by inference and embeddings.'
---

## InferenceRequest

`InferenceRequest` is the canonical payload passed to inference drivers.

```php
<?php
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$request = new InferenceRequest(
    messages: 'Summarize this text',
    model: 'gpt-4o-mini',
    options: ['temperature' => 0.2],
    mode: OutputMode::Text,
);
```

Most used accessors:

- `messages()`
- `model()`
- `tools()`, `toolChoice()`
- `responseFormat()`
- `options()`
- `outputMode()`
- `cachedContext()`
- `responseCachePolicy()`
- `retryPolicy()`

Most used mutators:

- `withMessages(...)`
- `withModel(...)`
- `withStreaming(...)`
- `withTools(...)`
- `withToolChoice(...)`
- `withResponseFormat(...)`
- `withOptions(...)`
- `withOutputMode(...)`
- `withCachedContext(...)`
- `withResponseCachePolicy(...)`
- `withRetryPolicy(...)`

## PendingInference

`PendingInference` defers execution until you read output.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$pending = Inference::using('openai')
    ->withMessages('Explain event sourcing in one paragraph.')
    ->create();

$text = $pending->get();
$json = $pending->asJsonData();
$response = $pending->response();
```

Core methods:

- `get()`: text content
- `asJson()`, `asJsonData()`
- `response()`: full `InferenceResponse`
- `stream()`: `InferenceStream` (only when streaming is enabled)
- `isStreamed()`

## InferenceStream

`InferenceStream` exposes partial snapshots during streaming.

```php
<?php
$stream = Inference::using('openai')
    ->withMessages('Write three short title ideas.')
    ->withStreaming(true)
    ->stream();

foreach ($stream->responses() as $partial) {
    echo $partial->contentDelta;
}

$final = $stream->final();
```

Core methods:

- `responses()`: generator of `PartialInferenceResponse`
- `all()`: collect all partial responses
- `final()`: materialized final `InferenceResponse`
- `onPartialResponse(callable)`
- `map(...)`, `filter(...)`, `reduce(...)`

## PartialInferenceResponse

Each streamed chunk is represented as a cumulative snapshot:

- `contentDelta` / `content()`
- `reasoningContentDelta` / `reasoningContent()`
- `toolId`, `toolName`, `toolArgs`
- `toolCalls()`
- `finishReason()`
- `usage()`

## EmbeddingsRequest and EmbeddingsResponse

Embeddings follow the same deferred pattern.

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

$pending = Embeddings::using('openai')
    ->withInputs(['doc one', 'doc two'])
    ->create();

$response = $pending->get();
$first = $response->first();
$vectors = $response->vectors();
$usage = $response->usage();
```

`EmbeddingsResponse` also provides `last()`, `split(...)`, `toValuesArray()`, and `toArray()`.

## Identity Types

IDs are value objects serialized as strings at boundaries:

- `InferenceRequestId`
- `InferenceExecutionId`
- `InferenceAttemptId`
- `InferenceResponseId`
- `PartialInferenceResponseId`
- `ToolCallId`
