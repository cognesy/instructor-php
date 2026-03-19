# Telemetry Package

Backend-neutral telemetry core for Instructor PHP.

Use it to correlate spans across runtimes and export them to OTEL, Logfire, or Langfuse.

## Live Interop Tests

The package now includes env-gated live backend interop tests under
`packages/telemetry/tests/Integration`.

Use them to prove both:

- write-path export to Logfire or Langfuse
- read-path queryback through the backend API

Run from the monorepo root:

```bash
TELEMETRY_INTEROP_ENABLED=1 composer test:telemetry-interop
```

Required env:

- Logfire: `LOGFIRE_TOKEN`, `LOGFIRE_OTLP_ENDPOINT`, `LOGFIRE_READ_TOKEN`
- Langfuse: `LANGFUSE_BASE_URL`, `LANGFUSE_PUBLIC_KEY`, `LANGFUSE_SECRET_KEY`
- inference, streaming, and agent runtime smoke tests also require `OPENAI_API_KEY`
- AgentCtrl smoke tests also require a usable `codex` CLI in `PATH`
  and a working Codex authentication/configuration state

The suite is opt-in by design. Without `TELEMETRY_INTEROP_ENABLED=1`, the
integration tests skip cleanly.

## Example

```php
<?php

use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

$telemetry = new Telemetry(
    registry: new TraceRegistry(),
    exporter: new LogfireExporter(new LogfireConfig(
        endpoint: 'https://logfire-eu.pydantic.dev',
        serviceName: 'demo',
        headers: ['Authorization' => $_ENV['LOGFIRE_TOKEN'] ?? ''],
    )),
);

$telemetry->openRoot('run', 'demo.run');
$telemetry->log('run', 'demo.step', AttributeBag::fromArray(['status' => 'ok']));
$telemetry->complete('run');
$telemetry->flush();
```

## Documentation

- `packages/telemetry/CHEATSHEET.md`
- `packages/telemetry/docs/01-introduction.md`
- `packages/telemetry/docs/02-basic-setup.md`
- `packages/telemetry/docs/03-runtime-wiring.md`
- `packages/telemetry/docs/04-troubleshooting.md`
- `packages/telemetry/docs/05-langfuse.md`
- `packages/telemetry/docs/06-logfire.md`
- `packages/telemetry/tests/Integration/`
- `examples/A03_Troubleshooting/TelemetryLogfire/run.php`
- `examples/A03_Troubleshooting/TelemetryLangfuse/run.php`
- `examples/D05_AgentTroubleshooting/TelemetryLogfire/run.php`
- `examples/D05_AgentTroubleshooting/TelemetryLangfuse/run.php`
- `examples/D05_AgentTroubleshooting/SubagentTelemetryLogfire/run.php`
- `examples/D05_AgentTroubleshooting/SubagentTelemetryLangfuse/run.php`
