---
title: 'Send LLM runtime telemetry to Logfire'
docname: 'llm_telemetry_logfire'
id: '4d3c'
tags:
  - 'telemetry'
  - 'logfire'
  - 'inference'
---
## Overview

This example uses `InferenceRuntime` directly and shows the Logfire connection
inline. It is useful when you want visibility into the raw LLM call path
without the additional StructuredOutput layer.

Key concepts:
- explicit `LogfireConfig` / `LogfireExporter` setup
- `InferenceRuntime`: direct Polyglot runtime for inference calls
- `PolyglotTelemetryProjector`: maps inference lifecycle events
- `HttpClientTelemetryProjector`: captures transport spans
- `Telemetry::flush()`: pushes the final batch to Logfire

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
use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;

$serviceName = 'examples.b03.telemetry-logfire';
$token = (string) Env::get('LOGFIRE_TOKEN', '');
if ($token === '') {
    throw new RuntimeException('Set LOGFIRE_TOKEN in .env to run this example.');
}
$endpoint = (string) Env::get('LOGFIRE_OTLP_ENDPOINT', '');
if ($endpoint === '') {
    throw new RuntimeException('Set LOGFIRE_OTLP_ENDPOINT in .env to run this example.');
}

$events = new EventDispatcher($serviceName);
$hub = new Telemetry(
    registry: new TraceRegistry(),
    exporter: new LogfireExporter(new LogfireConfig(
        endpoint: rtrim($endpoint, '/'),
        serviceName: $serviceName,
        headers: ['Authorization' => $token],
    )),
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
echo "Telemetry: flushed to Logfire\n";

assert($response->content() !== '');
?>
```
