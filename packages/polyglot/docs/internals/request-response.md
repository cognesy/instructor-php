---
title: Request and Response Objects
description: 'Core request/response types used by inference and embeddings.'
---

## InferenceRequest

`InferenceRequest` is the canonical payload passed to inference drivers.

```php
<?php
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

$request = new InferenceRequest(
    messages: 'Summarize this text',
    model: 'gpt-4o-mini',
    options: ['temperature' => 0.2],
);
```

Most used accessors:

- `messages()`
- `model()`
- `tools()`, `toolChoice()`
- `responseFormat()`
- `options()`
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
- `withCachedContext(...)`
- `withResponseCachePolicy(...)`
- `withRetryPolicy(...)`

## PendingInference

`PendingInference` defers execution until you read output. It is the raw pending-operation handle, not a base abstraction for higher layers.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$pending = Inference::using('openai')
    ->withMessages('Explain event sourcing in one paragraph.')
    ->create();

$text = $pending->get();
$json = $pending->asJsonData();
$toolData = $pending->asToolCallJsonData();
$response = $pending->response();
```

Core methods:

- `get()`: text content
- `asJson()`, `asJsonData()`
- `asToolCallJson()`, `asToolCallJsonData()`
- `response()`: full `InferenceResponse`
- `stream()`: `InferenceStream` (only when streaming is enabled)
- `isStreamed()`

Contract:

- lazy until first read
- one pending request coordinates one raw execution lifecycle
- mutable execution bookkeeping lives in an internal `InferenceExecutionSession`
- structured extraction/validation belongs outside Polyglot

## InferenceStream

`InferenceStream` exposes visible deltas during streaming.

```php
<?php
$stream = Inference::using('openai')
    ->withMessages('Write three short title ideas.')
    ->withStreaming(true)
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}

$final = $stream->final();
```

Core methods:

- `deltas()`: generator of `PartialInferenceDelta`
- `all()`: collect all visible deltas
- `final()`: materialized final `InferenceResponse`
- `onDelta(callable)`
- `map(...)`, `filter(...)`, `reduce(...)`

## PartialInferenceDelta

Each streamed chunk is represented as an ephemeral delta:

- `contentDelta`
- `reasoningContentDelta`
- `toolId`, `toolName`, `toolArgs`
- `finishReason`
- `usage`

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

## ResponseFormat

`ResponseFormat` is the explicit Polyglot value object for response-shape requests.

```php
<?php
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

$text = ResponseFormat::text();
$jsonObject = ResponseFormat::jsonObject();
$jsonSchema = ResponseFormat::jsonSchema(
    schema: ['type' => 'object'],
    name: 'schema',
    strict: true,
);
```

Drivers map these response-format values to provider-native request bodies.

## Identity Types

IDs are value objects serialized as strings at boundaries:

- `InferenceRequestId`
- `InferenceExecutionId`
- `InferenceAttemptId`
- `InferenceResponseId`
- `ToolCallId`
