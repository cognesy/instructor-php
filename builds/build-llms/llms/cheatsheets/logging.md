---
title: Logging
description: Structured logging pipeline — filters, enrichers, formatters, and log drivers
package: logging
---

# Logging Package Cheatsheet

Code-verified reference for `packages/logging`.

## Core Pipeline

`Cognesy\Logging\Pipeline\LoggingPipeline`:
- `LoggingPipeline::create(): self`
- `filter(EventFilter $filter): self`
- `enrich(EventEnricher $enricher): self`
- `format(EventFormatter $formatter): self`
- `write(LogWriter $writer): self`
- `build(): callable` (`callable(Event): void`)
- `__invoke(): callable` (returns `build()`)

`build()` snapshots the current pipeline configuration. Later builder mutations do not affect an already-built callable.

Minimal usage:

```php
use Cognesy\Events\Event;
use Cognesy\Logging\Enrichers\BaseEnricher;
use Cognesy\Logging\Formatters\DefaultFormatter;
use Cognesy\Logging\LogEntry;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\CallableWriter;

$pipeline = LoggingPipeline::create()
    ->enrich(new BaseEnricher())
    ->format(new DefaultFormatter())
    ->write(CallableWriter::create(function (LogEntry $entry): void {}))
    ->build();

$pipeline(new Event(['scope' => 'demo']));
```

## Contracts

- `EventFilter::__invoke(Event $event): bool`
- `EventEnricher::__invoke(Event $event): LogContext`
- `EventFormatter::__invoke(Event $event, LogContext $context): LogEntry`
- `LogWriter::__invoke(LogEntry $entry): void`
- `ContextProvider::getContext(): array`

## Value Objects

`LogEntry`:
- `LogEntry::create(string $level, string $message, array $context = [], ?DateTimeImmutable $timestamp = null, string $channel = 'default'): self`
- `withContext(array $additionalContext): self`
- `withLevel(string $level): self`
- `withMessage(string $message): self`
- `withChannel(string $channel): self`
- `isLevel(string $level): bool`
- `isLevelOrAbove(string $minimumLevel): bool`
- `jsonSerialize(): array`

`LogContext`:
- `LogContext::fromEvent(Event $event, array $additionalContext = []): self`
- `withFrameworkContext(array $context): self`
- `withPerformanceMetrics(array $metrics): self`
- `withUserContext(array $context): self`
- `toArray(): array`
- `jsonSerialize(): array`

## Built-In Filters

- `LogLevelFilter(string $minimumLevel = LogLevel::DEBUG)`
- `EventClassFilter(array $excludedClasses = [], array $includedClasses = [])`
- `EventHierarchyFilter(array $excludedClasses = [], array $includedClasses = [])`
- `CompositeFilter(EventFilter ...$filters)` (AND logic)

`EventHierarchyFilter` helpers:
- `EventHierarchyFilter::httpEventsOnly(): self`
- `EventHierarchyFilter::structuredOutputEventsOnly(): self`
- `EventHierarchyFilter::excludeHttpDebug(): self`

## Built-In Enrichers

- `BaseEnricher`
- `LazyEnricher(Closure $contextProvider, string $contextKey = 'framework')`
- `LazyEnricher::framework(Closure $provider): self`
- `LazyEnricher::metrics(Closure $provider): self`
- `LazyEnricher::user(Closure $provider): self`

## Built-In Formatters

- `DefaultFormatter(string $messageTemplate = '{event_class}: {message}', string $channel = 'instructor')`
- `MessageTemplateFormatter(array $templates = [], string $defaultTemplate = '{event_name}', string $channel = 'instructor')`

Template placeholders supported by `MessageTemplateFormatter`:
- `{event_class}`, `{event_name}`, `{event_id}`
- event-data placeholders like `{method}`, `{url}`
- framework placeholders like `{framework.request_id}`

## Built-In Writers

- `PsrLoggerWriter(LoggerInterface $logger)`
- `MonologChannelWriter(Logger $logger, bool $useEntryChannel = true)`
- `CallableWriter(Closure $writer)`
- `CallableWriter::create(callable $writer): self`
- `CompositeWriter(LogWriter ...$writers)`

## Framework Factories

Config keys:
- `channel` (string)
- `level` (string)
- `exclude_events` (`array<class-string>`)
- `include_events` (`array<class-string>`)
- `templates` (`array<string, string>`)

`SymfonyLoggingFactory`:
- `create(ContainerInterface $container, LoggerInterface $logger, array $config = []): callable`
- `defaultSetup(ContainerInterface $container, LoggerInterface $logger): callable`
- `productionSetup(ContainerInterface $container, LoggerInterface $logger): callable`

## Wiretap Integration

`Cognesy\Logging\Integrations\EventPipelineWiretap`:
- `EventPipelineWiretap(mixed $pipeline)` — wraps a `callable(Event): void` pipeline
- `__invoke(object $event): void` — forwards `Event` instances to the pipeline, ignores non-Event objects

## Zero-Config JSONL Logging (`EventLog`)

`Cognesy\Logging\EventLog` is a factory that replaces `new EventDispatcher(...)` in runtime default paths.
It automatically attaches a structured JSONL file sink when `INSTRUCTOR_LOG_PATH` is set in the environment.

### Enabling

```bash
# Shell: set env var before running your script
INSTRUCTOR_LOG_PATH=/tmp/instructor.jsonl php my-script.php

# Or from PHP (call once at bootstrap)
EventLog::enable('/tmp/instructor.jsonl');

# Or configure the whole default logging profile
EventLog::enable(new EventLogConfig(
    path: '/tmp/instructor.jsonl',
    level: LogLevel::DEBUG,
    excludeHttpDebug: true,
    stringClipLength: 2048,
));
```

### Usage in runtimes (internal)

```php
// Before
$events = $events ?? new EventDispatcher('instructor.structured-output.runtime');

// After
$events = $events ?? EventLog::root('instructor.structured-output.runtime');
```

`EventLog::root()` creates a plain dispatcher when no path is set — **no I/O, zero cost in tests**.
When `INSTRUCTOR_LOG_PATH` is set, it attaches a logging wiretap filtered at `INFO` level (default).

For child dispatchers that bubble to a root:

```php
$childEvents = EventLog::child('child-component', $parentEvents);
```

### API

`Cognesy\Logging\EventLog`:
- `EventLog::enable(string|EventLogConfig $config): void` — programmatic opt-in; call at bootstrap
- `EventLog::disable(): void` — reset programmatic opt-in
- `EventLog::root(string $name, ?LoggerInterface $logger = null): CanHandleEvents`
- `EventLog::child(string $name, CanHandleEvents $parent): CanHandleEvents`

### File-backed defaults

Default values now come from `packages/logging/resources/config/event_log.yaml`.
Override that file in app-level config by adding `config/event_log.yaml`.

Available knobs:
- `path`
- `level`
- `includeEvents`
- `excludeEvents`
- `useHierarchyFilter`
- `excludeHttpDebug`
- `includePayload`
- `includeCorrelation`
- `includeEventMetadata`
- `includeComponentMetadata`
- `stringClipLength`

Example:
```yaml
path: /tmp/instructor.jsonl
level: debug
excludeHttpDebug: true
excludeEvents:
  - Cognesy\\Http\\Events\\DebugRequestBodyUsed
stringClipLength: 2048
```

### Log level and overrides

Default minimum level: `INFO`. Override via env:
```bash
INSTRUCTOR_LOG_PATH=/tmp/instructor.jsonl INSTRUCTOR_LOG_LEVEL=debug php my-script.php
```

### JSONL format

Each line is a JSON object:
```json
{"timestamp":"2026-03-18T12:00:00+00:00","level":"info","channel":"instructor.structured-output.runtime","message":"StructuredOutputStarted","context":{"event_id":"...","event_class":"...","package":"instructor","component":"instructor.structured-output.runtime","correlation":{},"payload":{}}}
```

### Off by default

- No `INSTRUCTOR_LOG_PATH` → `EventLog::root()` returns a plain dispatcher, **no files written**
- No test detection heuristics — the empty path is the only gating signal
- Framework injections (Laravel, Symfony) pass `$events` explicitly → `EventLog::root()` never runs

### Callsite conventions (internal)

There are three situations and each has its own correct pattern:

**1. Runtime/builder with optional user-provided dispatcher**

Use `??` — EventLog only runs when no dispatcher is provided:

```php
// Correct: user's dispatcher takes precedence; EventLog only as default
$this->events = $events ?? EventLog::root('http.client.runtime');
```

**2. Configurator/builder that accepts a parent dispatcher**

When a parent is provided, create a plain child dispatcher — EventLog must NOT be involved.
When no parent is provided, use `EventLog::root()` as the standalone default:

```php
// Correct: user-provided parent → plain child dispatcher; standalone → EventLog
$events = $parentEvents !== null
    ? new EventDispatcher('agent-builder', $parentEvents)
    : EventLog::root('agent-builder');
```

Do NOT use `EventLog::child()` here — that would attach EventLog to a dispatcher the user
already controls, which may have its own logging configured.

**3. Zero-config static factory (no dispatcher parameter)**

Always use `EventLog::root()` directly — there is no user-supplied dispatcher to respect:

```php
// Correct: standalone factory, no parent
public static function default(): self {
    $events = EventLog::root('agent-loop');
    // ...
}
```

**Rule of thumb**: EventLog should be involved only when the code, not the user, is responsible
for creating the dispatcher. If the user passes anything in, bypass EventLog entirely.

### Observability components (internal)

- `EventLogConfig` — loads `event_log.yaml` defaults and applies env overrides
- `FileJsonLogWriter` — append-only JSONL sink; swallows write errors silently
- `StructuredEventFormatter` — normalizes Event → LogEntry with correlation fields

## Framework Integrations

Laravel:
- Laravel-specific logging integration lives in `packages/laravel`.

Symfony:
- `Cognesy\Logging\Integrations\Symfony\InstructorLoggingBundle`
- `InstructorLoggingExtension` registers `instructor_logging.pipeline_factory` and `instructor_logging.pipeline_listener`.
- `WiretapEventBusPass` adds one `wiretap()` method call to the configured event bus service.

Symfony config root (`instructor_logging`):
- `enabled` (`bool`, default `true`)
- `preset` (`default|production|custom`)
- `event_bus_service` (`string`, default `Cognesy\Events\Contracts\CanHandleEvents`)
- `config.channel` (`string`)
- `config.level` (`emergency|alert|critical|error|warning|notice|info|debug`)
- `config.exclude_events` (`string[]`)
- `config.include_events` (`string[]`)
- `config.templates` (`array<string, string>`)
