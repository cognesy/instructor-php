# Telemetry Package

Backend-neutral telemetry core for Instructor PHP.

Use it to correlate spans across runtimes and export them to OTEL, Logfire, or Langfuse.

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
- `examples/_support/logfire.php`
- `examples/_support/langfuse.php`
- `examples/D05_AgentTroubleshooting/TelemetryLogfire/run.php`
- `examples/D05_AgentTroubleshooting/TelemetryLangfuse/run.php`
