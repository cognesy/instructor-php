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
- `exclude_events` (array<class-string>)
- `include_events` (array<class-string>)
- `templates` (array<string, string>)

`SymfonyLoggingFactory`:
- `create(ContainerInterface $container, LoggerInterface $logger, array $config = []): callable`
- `defaultSetup(ContainerInterface $container, LoggerInterface $logger): callable`
- `productionSetup(ContainerInterface $container, LoggerInterface $logger): callable`

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
