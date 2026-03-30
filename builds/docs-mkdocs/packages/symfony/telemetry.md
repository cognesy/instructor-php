# Symfony Telemetry

`packages/symfony` now owns the baseline telemetry wiring for Symfony applications.

The package keeps telemetry optional, but once enabled it provides:

- package-owned exporter selection under `instructor.telemetry`
- projector composition for Instructor, Polyglot, HTTP, AgentCtrl, and native agents
- one shared runtime bridge attached to the package-owned event bus
- lifecycle-safe flush and shutdown hooks for HTTP, console, and Messenger worker contexts

## Minimal Setup

```yaml
instructor:
  telemetry:
    enabled: true
    driver: otel
    service_name: symfony

    drivers:
      otel:
        endpoint: '%env(INSTRUCTOR_TELEMETRY_OTEL_ENDPOINT)%'
# @doctest id="6b71"
```

This resolves:

- `Cognesy\Telemetry\Application\Telemetry`
- `Cognesy\Telemetry\Domain\Contract\CanExportObservations`
- `Cognesy\Telemetry\Application\Projector\CanProjectTelemetry`
- `Cognesy\Telemetry\Application\Projector\RuntimeEventBridge`

## Exporter Model

Supported `driver` values are:

- `null`
- `otel`
- `langfuse`
- `logfire`
- `composite`

Rules:

- `enabled: false` keeps the telemetry surface resolved but effectively no-op
- `driver: null` gives the package a predictable no-export baseline
- `driver: composite` fans out to `drivers.composite.exporters`
- exporters with incomplete credentials degrade to the null path instead of leaving autowiring ambiguous

Example composite setup:

```yaml
instructor:
  telemetry:
    enabled: true
    driver: composite

    drivers:
      composite:
        exporters: [otel, langfuse]

      otel:
        endpoint: '%env(INSTRUCTOR_TELEMETRY_OTEL_ENDPOINT)%'

      langfuse:
        host: '%env(INSTRUCTOR_TELEMETRY_LANGFUSE_HOST)%'
        public_key: '%env(INSTRUCTOR_TELEMETRY_LANGFUSE_PUBLIC_KEY)%'
        secret_key: '%env(INSTRUCTOR_TELEMETRY_LANGFUSE_SECRET_KEY)%'
# @doctest id="3f6d"
```

## Projector Selection

Runtime projectors are selected explicitly:

```yaml
instructor:
  telemetry:
    enabled: true

    projectors:
      instructor: true
      polyglot: true
      http: true
      agent_ctrl: true
      agents: true
# @doctest id="ec93"
```

This matters because the package event bus is the source of truth.
Telemetry hangs off that bus through one package-owned bridge instead of introducing a second observation path.

Disable a projector when you want to narrow the emitted runtime surface without replacing the rest of the telemetry graph.

## HTTP Chunk Capture

HTTP streaming chunk capture stays explicit:

```yaml
instructor:
  telemetry:
    http:
      capture_streaming_chunks: false
# @doctest id="0e61"
```

Leave it off by default for production noise control.
Enable it when you need deep streaming diagnostics.

## Lifecycle Behavior

The package now flushes or shuts down telemetry automatically in the baseline Symfony runtime shapes:

- HTTP: on kernel terminate
- Console: on command terminate and command error
- Messenger workers: after handled messages, after failed messages, and on worker stop

Applications can still override these services, but the default package path no longer depends on app-local cleanup callbacks.
