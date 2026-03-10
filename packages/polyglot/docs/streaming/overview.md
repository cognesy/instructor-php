---
title: Streaming
description: Stream LLM response deltas as they arrive for real-time output.
---

Streaming LLM responses allows your application to display content as it is generated, rather than waiting for the entire response to complete. This creates a more responsive experience for users and enables progressive processing of long outputs. Polyglot provides a consistent streaming API that works identically across all supported providers.


## Why Stream?

Streaming offers several practical advantages over waiting for a complete response:

- **Lower perceived latency.** Users see the first tokens almost immediately instead of staring at a blank screen.
- **Progressive processing.** You can begin acting on early output while later parts are still generating.
- **Efficient handling of long outputs.** Content is processed incrementally, avoiding large memory allocations and timeout risks.
- **Early termination.** You can break out of the stream when you have enough data, saving time and cost.


## Enabling Streaming

To stream a response, call `withStreaming()` on the `Inference` builder, then call `stream()` to obtain an `InferenceStream`. The `stream()` shortcut enables streaming automatically, so you may omit `withStreaming()` when using it:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Explain event buses in simple language.')
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}
```

The `deltas()` method returns a PHP `Generator` that yields `PartialInferenceDelta` objects one at a time. Each delta represents a single chunk received from the provider.


## What a Delta Contains

Every `PartialInferenceDelta` carries the incremental data from a single streaming event:

| Property | Description |
|---|---|
| `contentDelta` | New text content received in this chunk. |
| `reasoningContentDelta` | New reasoning / chain-of-thought content (for models that support it). |
| `toolName`, `toolArgs`, `toolId` | Tool call fragments streamed incrementally. |
| `finishReason` | Empty until the final chunk, then contains the stop reason (e.g. `stop`, `tool_calls`). |
| `usage` | Token usage statistics, when provided by the provider. |
| `value` | An optional arbitrary value attached to the delta by higher-level layers. |

Polyglot's `VisibilityTracker` automatically filters out invisible deltas (chunks that carry no meaningful change), so you only receive deltas that contain new content, tool data, or status changes.


## Retrieving the Final Response

After iterating through all deltas, you can obtain the complete `InferenceResponse` assembled from the stream:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Write a haiku about PHP.')
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}

$response = $stream->final();
echo $response->content();    // full accumulated text
echo $response->usage();      // token usage for the request
```

If you call `final()` before consuming the stream, it will drain all remaining deltas internally so that the response is fully assembled. This means you can skip the `foreach` loop entirely when you only need the final result, though in that case a non-streaming request would be more appropriate.


## Using Callbacks

You can register a callback with `onDelta()` instead of (or in addition to) iterating manually. The callback fires for every visible delta:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Write a short poem about queues.')
    ->stream()
    ->onDelta(fn($delta) => print($delta->contentDelta));

// Drain the stream to trigger the callbacks
$stream->final();
```

This pattern is convenient when the delta processing logic is simple and you want the final response at the end.


## Early Termination

You can break out of the delta loop at any time. This is useful when you have received enough content and want to stop processing:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Write a long story about space exploration.')
    ->stream();

$wordCount = 0;

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
    $wordCount += str_word_count($delta->contentDelta);

    if ($wordCount >= 100) {
        echo "\n[Stopped after ~100 words]\n";
        break;
    }
}
```

When you break out of the loop, the underlying HTTP connection to the provider continues in the background, but your application stops processing further chunks.


## Replay and Caching

The `deltas()` generator is one-shot by default -- once consumed, it cannot be iterated again. Attempting to call `deltas()` a second time will throw a `LogicException`.

If you need to replay the stream (for example, during testing or when multiple consumers need the same data), enable in-memory response caching before executing the request:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

$stream = Inference::using('openai')
    ->withMessages('Hello!')
    ->withResponseCachePolicy(ResponseCachePolicy::Memory)
    ->stream();
```

With `ResponseCachePolicy::Memory`, the raw response data is cached in memory, allowing the stream to be reconstructed if needed.


## Performance Considerations

When working with streaming responses, keep a few things in mind:

- **Memory.** If you are accumulating content yourself (e.g. building a string in a loop), be mindful of memory usage for very long responses. Consider writing chunks directly to a file or output buffer.
- **Output flushing.** In CLI scripts or streaming HTTP responses, flush the output buffer after each chunk so the user sees incremental output:
  ```php
  foreach ($stream->deltas() as $delta) {
      echo $delta->contentDelta;
      flush();
  }
  ```
- **Timeouts.** Long-running streams may exceed default HTTP timeout settings. Adjust your timeout configuration for requests that are expected to generate large amounts of content.
