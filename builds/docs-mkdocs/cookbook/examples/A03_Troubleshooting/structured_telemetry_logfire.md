---
title: 'Send StructuredOutput telemetry to Logfire'
docname: 'structured_telemetry_logfire'
id: 'b7a1'
tags:
  - 'troubleshooting'
  - 'telemetry'
  - 'logfire'
---
## Overview

This example shows the Logfire connection inline, using the actual Logfire
environment variables already present in this repository. It wires the existing
event bus into the telemetry projectors and then runs one small structured
extraction.

Key concepts:
- explicit `LogfireConfig` / `LogfireExporter` setup
- `RuntimeEventBridge`: attaches telemetry projection to the runtime event bus
- `LogfireExporter`: sends canonical telemetry to Logfire via OTLP/HTTP
- `InstructorTelemetryProjector`: maps structured output lifecycle events
- `PolyglotTelemetryProjector`: captures the nested LLM inference spans
- `HttpClientTelemetryProjector`: captures outbound HTTP spans

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;

class SupportTicket
{
    public string $priority;
    public string $summary;
}

$serviceName = 'examples.a03.telemetry-logfire';
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
    new InstructorTelemetryProjector($hub),
    new PolyglotTelemetryProjector($hub),
    new HttpClientTelemetryProjector($hub),
])))->attachTo($events);

$runtime = StructuredOutputRuntime::fromProvider(
    provider: LLMProvider::using('openai'),
    events: $events,
);

$ticket = (new StructuredOutput($runtime))
    ->with(
        messages: 'Customer report: The checkout page returns a 500 error after payment. Treat this as urgent and summarize it in one sentence.',
        responseModel: SupportTicket::class,
    )
    ->get();

$hub->flush();

echo "Priority: {$ticket->priority}\n";
echo "Summary: {$ticket->summary}\n";
echo "Telemetry: flushed to Logfire\n";

assert($ticket->priority !== '');
assert($ticket->summary !== '');
?>
```
