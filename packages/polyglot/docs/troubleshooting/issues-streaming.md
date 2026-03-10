---
title: Streaming Issues
description: Diagnose and resolve streaming response problems.
---

Streaming allows you to receive LLM responses incrementally as they are generated, rather than waiting for the complete response. Streaming issues typically involve premature termination, stream reuse, output buffering problems, or connection timeouts.

## Symptoms

- Streams cutting off prematurely
- `LogicException` with "Stream is exhausted and cannot be replayed"
- Partial or incomplete responses
- No output appearing during streaming (buffering issue)
- Connection timeouts during long-running streams

## Enable Streaming Correctly

Streaming must be explicitly enabled on the request. The simplest approach is to use the `stream()` shortcut on the inference builder, then consume the stream via `deltas()`:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Write a short poem about the ocean.')
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}
```

Alternatively, use the `withStreaming()` method followed by `create()->stream()`:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$pending = Inference::using('openai')
    ->withMessages('Write a short poem about the ocean.')
    ->withStreaming(true)
    ->create();

$stream = $pending->stream();
foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}
```

## Do Not Consume a Stream Twice

The most common streaming mistake is attempting to iterate over `deltas()` more than once. Streams are single-pass by design. A second call to `deltas()` throws a `LogicException`.

```php
<?php

// This will throw LogicException on the second loop
foreach ($stream->deltas() as $delta) { /* first pass */ }
foreach ($stream->deltas() as $delta) { /* throws! */ }
```

If you need to replay the stream content, enable the memory cache policy before creating the request:

```php
<?php

use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::using('openai')
    ->withResponseCachePolicy(ResponseCachePolicy::Memory)
    ->withMessages('Write a haiku.')
    ->withStreaming(true);
```

## Collect the Full Response After Streaming

To get the complete assembled response after consuming all deltas, use the `final()` method on the stream:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Explain gravity.')
    ->stream();

// Consume deltas for real-time output
foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
    flush();
}

// Get the finalized response (assembled from all deltas)
$response = $stream->final();
echo "\n\nTotal tokens: " . $response->usage()->total() . "\n";
```

If you only need the final response and do not need to process deltas, call `final()` directly -- it will drain the stream internally.

## Flush Output Buffers

When streaming to a browser or CLI, PHP's output buffering can delay visible output. Flush buffers explicitly after each delta:

```php
<?php

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;

    // Flush PHP output buffer
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}
```

For web applications, also ensure that your web server is not buffering the response. Common server-side buffering sources:

- **Nginx** -- disable proxy buffering with `proxy_buffering off;` or set the response header `X-Accel-Buffering: no`
- **Apache mod_deflate / mod_gzip** -- compression modules buffer output; disable them for streaming endpoints
- **PHP output buffering** -- check `output_buffering` in `php.ini` and consider calling `ob_end_flush()` before streaming begins

## Handle Connection Timeouts

Streaming responses can take longer than non-streaming requests because the connection remains open while the model generates tokens. Increase the timeout settings to accommodate this:

```php
<?php

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$httpClient = HttpClient::fromConfig(new HttpClientConfig(
    connectTimeout: 10,
    requestTimeout: 180,  // 3 minutes for long-running streams
    idleTimeout: 60,      // 60 seconds between chunks
));

$runtime = InferenceRuntime::fromConfig(
    config: LLMConfig::fromPreset('openai'),
    httpClient: $httpClient,
);

$stream = Inference::fromRuntime($runtime)
    ->withMessages('Write a long story about a space explorer.')
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
    flush();
}
```

The `idleTimeout` is particularly important for streaming. It controls how long the client waits for the next chunk before giving up. If a model pauses while generating (for example, during complex reasoning), a short idle timeout will cause the stream to terminate prematurely.

## Handle Errors During Streaming

Wrap the stream consumption in a try-catch to handle errors that occur mid-stream. This is important because errors can arise after some deltas have already been received:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Write a detailed explanation of relativity.')
    ->stream();

$content = '';

try {
    foreach ($stream->deltas() as $delta) {
        $content .= $delta->contentDelta;
        echo $delta->contentDelta;
        flush();
    }
} catch (\Exception $e) {
    echo "\nStream error: " . $e->getMessage() . "\n";

    if (!empty($content)) {
        echo "Partial content received: " . strlen($content) . " characters\n";
    }
}
```

## Use the onDelta Callback

Instead of iterating over `deltas()`, you can register a callback that is invoked for each visible delta:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Tell me a joke.')
    ->stream();

$stream->onDelta(function ($delta) {
    echo $delta->contentDelta;
    flush();
});

// Drain the stream to trigger all callbacks
$stream->all();
```

## Use Functional Stream Operations

The stream supports `map()`, `filter()`, and `reduce()` operations for functional-style processing:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('List five programming languages.')
    ->stream();

// Collect only non-empty content deltas
$content = $stream->reduce(
    fn(string $carry, $delta) => $carry . $delta->contentDelta,
    '',
);

echo $content;
```

## Fallback to Non-Streaming

If streaming consistently fails for a particular model or provider, fall back to a non-streaming request:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

function getResponse(string $prompt, bool $preferStreaming = true): string {
    $inference = Inference::using('openai')->withMessages($prompt);

    if ($preferStreaming) {
        try {
            $content = '';
            foreach ($inference->stream()->deltas() as $delta) {
                $content .= $delta->contentDelta;
            }
            return $content;
        } catch (\Exception $e) {
            // Fall through to non-streaming
        }
    }

    return $inference->get();
}
```

## Verify Model Supports Streaming

Not all models support streaming. If enabling streaming causes errors, test with a plain non-streaming request first. If the non-streaming request succeeds, the model may not support streaming, or the provider may require a different endpoint for streamed responses.

## Common Pitfalls

- **Consuming `deltas()` twice.** This is the most frequent mistake. Use `ResponseCachePolicy::Memory` if you need to replay.
- **Not flushing output.** Without explicit `flush()` calls, PHP buffers output and the user sees nothing until the stream completes.
- **Short timeouts.** The default 30-second request timeout is too short for many streaming responses. Increase `requestTimeout` and `idleTimeout`.
- **Ignoring partial content on error.** When a stream error occurs mid-way, you may have already received useful content. Always capture partial content in your error handler.
- **Server-side buffering.** Even with PHP `flush()`, Nginx or Apache may buffer the response. Configure your web server to pass through responses immediately for streaming endpoints.
