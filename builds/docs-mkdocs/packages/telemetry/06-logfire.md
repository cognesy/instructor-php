---
title: 'Logfire Setup'
description: 'Minimal setup for exporting telemetry to Logfire'
---

# Logfire Setup

The simplest Logfire setup uses:

- `LogfireConfig`
- `LogfireExporter`
- `Telemetry`

## Minimal Example

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
        headers: ['Authorization' => $_ENV['LOGFIRE_TOKEN']],
    )),
);
// @doctest id="3f6c"
```

## Environment Variables

The example helpers look for:

- `LOGFIRE_TOKEN`
- `LOGFIRE_API_TOKEN`
- `LOGFIRE_OTLP_ENDPOINT`
- `LOGFIRE_BASE_URL`

See:

- `examples/_support/logfire.php`

## Endpoint Rule

`LogfireConfig` expects the OTLP base endpoint. Do not pass a full `/v1/traces`
or `/v1/metrics` path.

The helper in `examples/_support/logfire.php` strips those suffixes if present.

## Agent Runtime Example

The working agent example is:

- `examples/D05_AgentTroubleshooting/TelemetryLogfire/run.php`

## Subagent Example

For nested parent and child traces, see:

- `examples/D05_AgentTroubleshooting/SubagentTelemetryLogfire/run.php`

## Notes

- `LogfireExporter` requires either `LogfireConfig` or a custom transport
- service name comes from `LogfireConfig::serviceName()`
- if export fails with a 4xx response, check the token and endpoint first
