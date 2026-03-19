# Telemetry Cheatsheet

Namespace:

- `Cognesy\\Telemetry\\`

Minimal usage:

```php
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;

$telemetry = new Telemetry(
    registry: new TraceRegistry(),
    exporter: new OtelExporter(),
);

$telemetry->openRoot('run', 'demo.run');
$telemetry->log('run', 'demo.step');
$telemetry->complete('run');
$telemetry->flush();
```

Core concepts:

- `TraceContext`
- `SpanReference`
- `Observation`
- `MetricMeasurement`
- `TelemetryContinuation`
- `InstrumentationProfile`

Application services:

- `Telemetry`
- `TraceRegistry`
- `CompositeTelemetryExporter`

Exporter setup:

- `Telemetry` takes `new TraceRegistry()` plus an exporter
- use `CompositeTelemetryExporter([...])` when you want to fan out to multiple backends
- call `flush()` after the run to send buffered observations and metrics

## Logfire

Basic setup:

```php
use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;

$telemetry = new Telemetry(
    registry: new TraceRegistry(),
    exporter: new LogfireExporter(new LogfireConfig(
        endpoint: 'https://logfire-eu.pydantic.dev',
        serviceName: 'my-service',
        headers: ['Authorization' => $token],
    )),
);
```

Notes:

- `endpoint` is the base OTLP URL, not the full `/v1/traces` path
- `LogfireExporter` requires either `LogfireConfig` or a custom transport
- working helper: `examples/_support/logfire.php`
- practical examples:
  - `examples/D05_AgentTroubleshooting/TelemetryLogfire/run.php`
  - `examples/D05_AgentTroubleshooting/SubagentTelemetryLogfire/run.php`

## Langfuse

Basic setup:

```php
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseConfig;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseExporter;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseHttpTransport;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;

$telemetry = new Telemetry(
    registry: new TraceRegistry(),
    exporter: new LangfuseExporter(
        transport: new LangfuseHttpTransport(new LangfuseConfig(
            baseUrl: $baseUrl,
            publicKey: $publicKey,
            secretKey: $secretKey,
        )),
    ),
);
```

Notes:

- Langfuse traces are sent to `/api/public/otel/v1/traces`
- request-scoped traces fall back to request ids as `session.id` values
- working helper: `examples/_support/langfuse.php`
- practical examples:
  - `examples/D05_AgentTroubleshooting/TelemetryLangfuse/run.php`
  - `examples/D05_AgentTroubleshooting/SubagentTelemetryLangfuse/run.php`

## Runtime wiring

For runtime packages, telemetry is usually attached to an event bus through `RuntimeEventBridge`
and one or more projectors:

```php
use Cognesy\Agents\Telemetry\AgentsTelemetryProjector;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;

(new RuntimeEventBridge(new CompositeTelemetryProjector([
    new AgentsTelemetryProjector($telemetry),
    new PolyglotTelemetryProjector($telemetry),
    new HttpClientTelemetryProjector($telemetry),
])))->attachTo($events);
```

Runtime notes:

- runtime packages own their local projectors and emit serialized `data['telemetry']` envelopes
- telemetry core rehydrates the envelope and correlates spans centrally
- `AgentCtrlTelemetryProjector` correlates `agent-ctrl` by `executionId`
- `agent_ctrl.session_id` is continuation metadata, not the primary trace key
- request-scoped traces fall back to request ids as `session.id` values for Langfuse

Adapter namespaces:

- `Cognesy\\Telemetry\\Adapters\\OTel`
- `Cognesy\\Telemetry\\Adapters\\Logfire`
- `Cognesy\\Telemetry\\Adapters\\Langfuse`
