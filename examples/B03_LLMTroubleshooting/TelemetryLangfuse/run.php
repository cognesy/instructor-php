---
title: 'Send LLM runtime telemetry to Langfuse'
docname: 'llm_telemetry_langfuse'
id: '76c8'
tags:
  - 'telemetry'
  - 'langfuse'
  - 'inference'
---
## Overview

This example uses `InferenceRuntime` directly and shows the Langfuse connection
inline. It is useful when you want visibility into the raw LLM call path
without the additional StructuredOutput layer.

Key concepts:
- explicit `LangfuseConfig` / `LangfuseHttpTransport` / `LangfuseExporter` setup
- `InferenceRuntime`: direct Polyglot runtime for inference calls
- `PolyglotTelemetryProjector`: maps inference lifecycle events
- `HttpClientTelemetryProjector`: captures transport spans
- `Telemetry::flush()`: pushes the final batch to Langfuse

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseConfig;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseExporter;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseHttpTransport;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;

$serviceName = 'examples.b03.telemetry-langfuse';
$baseUrl = (string) Env::get('LANGFUSE_BASE_URL', '');
if ($baseUrl === '') {
    throw new RuntimeException('Set LANGFUSE_BASE_URL in .env to run this example.');
}
$publicKey = (string) Env::get('LANGFUSE_PUBLIC_KEY', '');
if ($publicKey === '') {
    throw new RuntimeException('Set LANGFUSE_PUBLIC_KEY in .env to run this example.');
}
$secretKey = (string) Env::get('LANGFUSE_SECRET_KEY', '');
if ($secretKey === '') {
    throw new RuntimeException('Set LANGFUSE_SECRET_KEY in .env to run this example.');
}

$events = new EventDispatcher($serviceName);
$hub = new Telemetry(
    registry: new TraceRegistry(),
    exporter: new LangfuseExporter(
        transport: new LangfuseHttpTransport(new LangfuseConfig(
            baseUrl: $baseUrl,
            publicKey: $publicKey,
            secretKey: $secretKey,
        )),
    ),
);

(new RuntimeEventBridge(new CompositeTelemetryProjector([
    new PolyglotTelemetryProjector($hub),
    new HttpClientTelemetryProjector($hub),
])))->attachTo($events);

$runtime = InferenceRuntime::fromProvider(
    provider: LLMProvider::using('openai'),
    events: $events,
);

$response = Inference::fromRuntime($runtime)
    ->with(
        messages: Messages::fromString('Summarize why observability matters for LLM applications in exactly 3 bullet points.'),
        options: ['max_tokens' => 180],
    )
    ->response();

$hub->flush();

echo "Response:\n";
echo $response->content() . "\n\n";
if ($response->usage() !== null) {
    echo "Tokens: {$response->usage()->inputTokens} in / {$response->usage()->outputTokens} out\n";
}
echo "Telemetry: flushed to Langfuse\n";

assert($response->content() !== '');
?>
```
