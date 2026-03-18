---
title: 'Send streaming LLM telemetry to Langfuse'
docname: 'llm_telemetry_streaming_langfuse'
id: '3b9e'
tags:
  - 'telemetry'
  - 'langfuse'
  - 'streaming'
---
## Overview

This example uses `InferenceRuntime` with **streaming** enabled and sends the
full LLM and HTTP lifecycle — including the complete response body — to Langfuse.

For streaming responses the HTTP span stays open while chunks arrive and closes
only when the stream is exhausted (`HttpStreamCompleted`). By enabling
`captureStreamingChunks: true` each SSE chunk is also recorded as a log event
under the `http.client.request` span, which is useful for debugging but should
be left off in production.

Key concepts:
- `withStreaming()`: requests a server-sent-events stream from the LLM provider
- `HttpClientTelemetryProjector($hub, captureStreamingChunks: true)`: records
  each chunk and closes the HTTP span with the full body on stream completion
- `HttpStreamCompleted`: new event fired when the stream generator is exhausted
- `Telemetry::flush()`: must be called **after** the stream is consumed

## Example

```php
<?php
require 'examples/boot.php';
require_once 'examples/_support/langfuse.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;

$events = new EventDispatcher('examples.b03.telemetry-streaming-langfuse');
$hub = exampleLangfuseHub();

(new RuntimeEventBridge(new CompositeTelemetryProjector([
    new PolyglotTelemetryProjector($hub),
    // captureStreamingChunks: true logs every SSE chunk as an event under
    // the http.client.request span — great for debugging, disable in production
    new HttpClientTelemetryProjector($hub, captureStreamingChunks: true),
])))->attachTo($events);

$runtime = InferenceRuntime::fromProvider(
    provider: LLMProvider::using('openai'),
    events: $events,
);

$stream = Inference::fromRuntime($runtime)
    ->with(
        messages: Messages::fromString('Explain in 3 bullet points why distributed tracing matters for streaming AI responses.'),
        options: ['max_tokens' => 200],
    )
    ->withStreaming()
    ->stream();

echo "Response (streaming):\n";
$fullContent = '';
foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
    $fullContent .= $delta->contentDelta;
}
echo "\n\n";

// Flush AFTER the stream is fully consumed: HttpStreamCompleted fires when
// the generator above is exhausted, carrying the full raw HTTP response body.
$hub->flush();

echo "Telemetry: flushed to Langfuse\n";

assert($fullContent !== '');
?>
```
