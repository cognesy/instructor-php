---
title: Streaming
description: Stream visible response deltas as they arrive.
---

Enable streaming with `withStreaming()` and consume the returned `InferenceStream`.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Explain event buses in simple language.')
    ->withStreaming()
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}

$final = $stream->final();
```

## What a Delta Contains

Each `PartialInferenceDelta` can carry:

- `contentDelta`
- `reasoningContentDelta`
- tool call fragments
- `finishReason`
- `usage`

## Replay

`deltas()` is one-shot by default.

If you need replay, set `withResponseCachePolicy(ResponseCachePolicy::Memory)` before executing the request.
