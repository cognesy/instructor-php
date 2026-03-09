---
title: Response Handling
description: Work with `PendingInference`, `InferenceResponse`, and streams.
---

`create()` returns `PendingInference`, a lazy handle for one inference operation.

It does not call the provider until you ask for data.

## The Main Accessors

- `get()` returns response text
- `response()` returns `InferenceResponse`
- `asJsonData()` decodes `content()` as JSON
- `asToolCallJsonData()` extracts tool-call arguments as arrays
- `stream()` returns `InferenceStream`

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$pending = Inference::using('openai')
    ->withMessages('Return JSON with a single "status" field.')
    ->withResponseFormat(['type' => 'json_object'])
    ->create();

$data = $pending->asJsonData();
```

## `InferenceResponse`

The normalized response object exposes the fields most apps need:

- `content()`
- `reasoningContent()`
- `toolCalls()`
- `usage()`
- `finishReason()`
- `responseData()`

## Streaming

If the request is streamed, iterate over visible deltas:

```php
<?php

$stream = Inference::using('openai')
    ->withMessages('Explain queues in simple terms.')
    ->withStreaming()
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}

$final = $stream->final();
```

`deltas()` is one-shot. If you need replay, opt into `ResponseCachePolicy::Memory`.
