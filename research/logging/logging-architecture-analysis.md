# Logging Integration Architecture Analysis

## Executive Summary

The current proposal for logging integration introduces multiple anti-patterns that will not scale across frameworks, increase maintenance burden, and create tight coupling between domains. This document analyzes the architectural flaws and proposes a DDD-aligned, functional solution leveraging the existing event-driven architecture.

## Critical Flaws in Current Proposal

### 1. Violation of Single Responsibility Principle

**Problem**: The proposal adds `LoggerAware` interface and `HandlesLogging` trait directly to domain classes (`StructuredOutput`, `Inference`, `Embeddings`).

```php
// ANTI-PATTERN: Domain classes now concerned with logging infrastructure
class StructuredOutput implements LoggerAware
{
    use HandlesLogging;

    private LoggerInterface $logger;
    private bool $loggingEnabled = false;
    private string $logLevel = LogLevel::DEBUG;
    private array $logContext = [];
    // ... domain logic mixed with logging concerns
}
```

**Why This Fails**:
- Domain classes are now responsible for logging configuration AND business logic
- Every domain class needs to implement logging interfaces and manage logging state
- Breaks Open/Closed Principle - domain classes must change to add logging features
- Violates Domain-Driven Design - infrastructure concerns pollute the domain layer

### 2. Framework-Specific Service Provider Explosion

**Problem**: Each framework requires a custom ServiceProvider with manual wiring logic.

```php
// ANTI-PATTERN: Framework-specific boilerplate that duplicates logic
class InstructorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StructuredOutput::class, function ($app) {
            $instance = new StructuredOutput(/* ... */);

            if ($this->shouldAutoEnableLogging()) {
                $this->configureLogging($instance, $app); // Manual wiring
            }

            return $instance;
        });

        // Repeat for Inference, Embeddings, etc.
    }

    private function configureLogging($instance, $app): void
    {
        // Framework-specific context provider setup
        $contextProviders = [new LaravelContextProvider($app['request'])];
        $converter = new EventLogConverter(/* ... */);
        $instance->wiretap($converter); // Manual wiretap setup
    }
}
```

**Why This Fails**:
- Each new framework requires a new ServiceProvider with duplicated logic
- Each new domain class requires adding registration code to every ServiceProvider
- No clear separation between registration logic and configuration logic
- Context providers are tightly coupled to framework implementations
- Testing requires mocking entire framework containers

### 3. Event-to-Log Converter as Stateful Service

**Problem**: `EventLogConverter` is a stateful object that must be configured per-instance.

```php
// ANTI-PATTERN: Stateful converter with configuration mixed with behavior
class EventLogConverter
{
    public function __construct(
        private LoggerInterface $logger,
        private array $contextProviders = [],  // Stateful
        private string $minimumLevel = LogLevel::DEBUG,  // Stateful
        private bool $includeMetrics = true,  // Stateful
    ) {}

    public function __invoke(Event $event): void
    {
        // Side-effectful logging operation
    }
}
```

**Why This Fails**:
- Must instantiate a new converter for each configuration
- No clear composition model for adding/removing context providers
- Context providers are array of objects - not composable
- Metrics collection mixed with logging concerns
- No way to test filtering logic without instantiating entire converter

### 4. Missing Abstraction Over Context Providers

**Problem**: Context providers are framework-specific implementations with no standard composition model.

```php
// ANTI-PATTERN: Framework-specific implementation with hard dependencies
class LaravelContextProvider implements ContextProvider
{
    public function __construct(private ?Request $request = null) {}

    public function getContext(): array
    {
        // Returns flat array - not composable
        return [
            'request_id' => $this->request->header('X-Request-ID'),
            'user_id' => optional($this->request->user())->id,
            // ... framework-specific calls
        ];
    }
}
```

**Why This Fails**:
- Tight coupling to Laravel's Request object
- No standard way to compose multiple context providers
- Returns flat array - order matters, no conflict resolution
- No lazy evaluation - always computes all context
- Cannot be tested without Laravel framework

### 5. Configuration Complexity Without Clear Boundaries

**Problem**: Configuration is scattered across multiple layers with no clear ownership.

```php
// config/instructor.php - Application layer
'logging' => [
    'auto_enable' => true,
    'channel' => 'instructor',
    'level' => 'debug',
    'filters' => [/* ... */],
    'formatters' => [/* ... */],
],

// Domain layer - duplicates configuration concerns
$output->withLogging()
    ->withLogLevel('info')
    ->withLogContext(['feature' => 'user_extraction'])
    ->withMetricsLogging();
```

**Why This Fails**:
- Configuration exists in both application config files AND domain objects
- No clear precedence rules - which wins: file config or fluent API?
- Domain objects now manage infrastructure configuration
- Testing requires setting up both config files AND object state

### 6. Performance Overhead Through Indirection

**Problem**: Multiple layers of abstraction with no lazy evaluation strategy.

```php
// Every event goes through multiple layers
Event -> EventDispatcher -> Wiretap -> EventLogConverter -> ContextProviders -> Logger
```

**Why This Fails**:
- Context providers always execute, even if log level filtered out
- Metrics collection always happens, even if disabled
- No short-circuit evaluation based on log level
- Array copying and merging on every event

## Architectural Root Causes

### 1. Misunderstanding of Event-Driven Architecture

The proposal treats the event system as a message bus that needs decoration, rather than recognizing that **the event system IS the logging abstraction layer**.

**Current Architecture Already Provides**:
- Structured event objects with log levels
- PSR-14 compliant dispatcher
- Wiretap pattern for observability
- Event hierarchy for filtering
- Framework adapters (LaravelEventDispatcher, SymfonyEventDispatcher)

**What's Actually Missing**: A **composable, functional event listener** that can be configured without polluting domain objects.

### 2. Object-Oriented Bias Over Functional Composition

The proposal creates stateful objects with dependencies, rather than composing pure functions.

**OOP Approach** (proposed):
```php
$converter = new EventLogConverter($logger, $contextProviders, $level, $metrics);
$instance->wiretap($converter);
```

**Functional Approach** (better):
```php
$listener = compose(
    filter(byLogLevel($threshold)),
    addContext($contextProviders),
    format($formatter),
    log($logger)
);
$instance->wiretap($listener);
```

### 3. Framework Integration at Wrong Layer

Framework integration should happen at the **infrastructure boundary**, not the **application boundary**.

**Current Proposal**: Framework ServiceProviders configure domain objects directly
**Better Approach**: Framework packages provide pre-configured listeners that wire into existing event system

## Proposed Architecture: Functional Event Listener Pipeline

### Core Design Principles

1. **Domain Layer Purity**: Domain classes (`StructuredOutput`, `Inference`) remain unchanged - no logging interfaces
2. **Functional Composition**: Logging pipeline built from composable, pure functions
3. **Infrastructure Boundary**: Framework integration via listener factories, not service providers
4. **Lazy Evaluation**: Context providers and formatters only execute when needed
5. **Type Safety**: Leverage PHP 8.2+ union types, enums, readonly properties

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      Domain Layer                           │
│  StructuredOutput, Inference, Embeddings                    │
│  (unchanged - dispatch events via HandlesEvents trait)      │
└────────────────────────┬────────────────────────────────────┘
                         │ emits Event objects
                         ↓
┌─────────────────────────────────────────────────────────────┐
│                   Event System (existing)                   │
│  EventDispatcher → Wiretaps → Class Listeners               │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ↓
┌─────────────────────────────────────────────────────────────┐
│              Logging Pipeline (NEW)                         │
│                                                              │
│  EventListener = Pipeline<Event, void>                      │
│    = Filter → Enrich → Format → Log                         │
│                                                              │
│  Each stage is a pure function:                             │
│    Filter: Event → Option<Event>                            │
│    Enrich: Event → Event + Context                          │
│    Format: Event + Context → LogEntry                       │
│    Log: LogEntry → IO<void>                                 │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ↓
┌─────────────────────────────────────────────────────────────┐
│         Framework Integration (Infrastructure)              │
│                                                              │
│  Laravel: LoggingListenerFactory::forLaravel($container)    │
│  Symfony: LoggingListenerFactory::forSymfony($container)    │
│  Standalone: LoggingListenerFactory::standalone($config)    │
└─────────────────────────────────────────────────────────────┘
```

## Implementation Design

### 1. Core Value Objects

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging;

use DateTimeImmutable;

/**
 * Immutable log entry - output of formatting pipeline
 */
final readonly class LogEntry
{
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
        public DateTimeImmutable $timestamp,
    ) {}
}

/**
 * Immutable context data - functional alternative to ContextProvider
 */
final readonly class LogContext
{
    public function __construct(
        public array $data = [],
    ) {}

    public function merge(LogContext $other): LogContext
    {
        return new LogContext([...$this->data, ...$other->data]);
    }

    public static function from(array $data): LogContext
    {
        return new LogContext($data);
    }
}

/**
 * Log level filtering configuration
 */
enum LogThreshold: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case NOTICE = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
    case ALERT = 'alert';
    case EMERGENCY = 'emergency';

    public function allows(string $level): bool
    {
        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        $thresholdIndex = array_search($this->value, $levels, true);
        $levelIndex = array_search($level, $levels, true);

        return $levelIndex !== false && $levelIndex >= $thresholdIndex;
    }
}
```

### 2. Functional Pipeline Types

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging\Pipeline;

use Cognesy\Events\Event;
use Cognesy\Instructor\Logging\LogContext;
use Cognesy\Instructor\Logging\LogEntry;
use Cognesy\Utils\Result\Option;

/**
 * Filter stage: Event → Option<Event>
 * Returns None if event should not be logged
 */
interface EventFilter
{
    public function __invoke(Event $event): Option;
}

/**
 * Enrichment stage: Event → (Event, LogContext)
 * Adds contextual information without mutating event
 */
interface EventEnricher
{
    public function __invoke(Event $event): array; // [Event, LogContext]
}

/**
 * Format stage: (Event, LogContext) → LogEntry
 * Transforms event + context into structured log entry
 */
interface EventFormatter
{
    public function __invoke(Event $event, LogContext $context): LogEntry;
}

/**
 * Log stage: LogEntry → void
 * Side effect - writes to logger
 */
interface LogWriter
{
    public function __invoke(LogEntry $entry): void;
}
```

### 3. Composable Filter Implementations

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging\Filters;

use Cognesy\Events\Event;
use Cognesy\Instructor\Logging\Pipeline\EventFilter;
use Cognesy\Instructor\Logging\LogThreshold;
use Cognesy\Utils\Result\Option;

/**
 * Filter by log level threshold
 */
final readonly class LogLevelFilter implements EventFilter
{
    public function __construct(
        private LogThreshold $threshold,
    ) {}

    public function __invoke(Event $event): Option
    {
        return $this->threshold->allows($event->logLevel)
            ? Option::some($event)
            : Option::none();
    }
}

/**
 * Filter by event class
 */
final readonly class EventClassFilter implements EventFilter
{
    /**
     * @param array<class-string> $allowedClasses
     */
    public function __construct(
        private array $allowedClasses,
    ) {}

    public function __invoke(Event $event): Option
    {
        foreach ($this->allowedClasses as $class) {
            if ($event instanceof $class) {
                return Option::some($event);
            }
        }
        return Option::none();
    }
}

/**
 * Sampling filter - logs only X% of events
 */
final readonly class SamplingFilter implements EventFilter
{
    public function __construct(
        private float $rate, // 0.0 to 1.0
    ) {}

    public function __invoke(Event $event): Option
    {
        return (mt_rand() / mt_getrandmax()) <= $this->rate
            ? Option::some($event)
            : Option::none();
    }
}

/**
 * Compose multiple filters with AND logic
 */
final readonly class CompositeFilter implements EventFilter
{
    /**
     * @param array<EventFilter> $filters
     */
    public function __construct(
        private array $filters,
    ) {}

    public function __invoke(Event $event): Option
    {
        foreach ($this->filters as $filter) {
            $result = $filter($event);
            if ($result->isNone()) {
                return Option::none();
            }
        }
        return Option::some($event);
    }
}
```

### 4. Composable Enricher Implementations

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging\Enrichers;

use Cognesy\Events\Event;
use Cognesy\Instructor\Logging\Pipeline\EventEnricher;
use Cognesy\Instructor\Logging\LogContext;

/**
 * Adds static context data
 */
final readonly class StaticContextEnricher implements EventEnricher
{
    public function __construct(
        private LogContext $context,
    ) {}

    public function __invoke(Event $event): array
    {
        return [$event, $this->context];
    }
}

/**
 * Adds dynamic context via lazy evaluation
 */
final readonly class LazyContextEnricher implements EventEnricher
{
    /**
     * @param callable(): array $contextProvider
     */
    public function __construct(
        private mixed $contextProvider,
    ) {}

    public function __invoke(Event $event): array
    {
        $dynamicContext = ($this->contextProvider)();
        return [$event, LogContext::from($dynamicContext)];
    }
}

/**
 * Adds event metadata
 */
final readonly class EventMetadataEnricher implements EventEnricher
{
    public function __invoke(Event $event): array
    {
        $context = LogContext::from([
            'event_id' => $event->id,
            'event_class' => $event::class,
            'event_name' => $event->name(),
            'timestamp' => $event->createdAt->format(\DateTime::ISO8601),
        ]);

        return [$event, $context];
    }
}

/**
 * Compose multiple enrichers - merges contexts
 */
final readonly class CompositeEnricher implements EventEnricher
{
    /**
     * @param array<EventEnricher> $enrichers
     */
    public function __construct(
        private array $enrichers,
    ) {}

    public function __invoke(Event $event): array
    {
        $mergedContext = LogContext::from([]);

        foreach ($this->enrichers as $enricher) {
            [$event, $context] = $enricher($event);
            $mergedContext = $mergedContext->merge($context);
        }

        return [$event, $mergedContext];
    }
}
```

### 5. Formatter Implementations

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging\Formatters;

use Cognesy\Events\Event;
use Cognesy\Instructor\Logging\Pipeline\EventFormatter;
use Cognesy\Instructor\Logging\LogContext;
use Cognesy\Instructor\Logging\LogEntry;

/**
 * Standard formatter - event message + merged context
 */
final readonly class StandardFormatter implements EventFormatter
{
    public function __invoke(Event $event, LogContext $context): LogEntry
    {
        $message = $event->name();
        $mergedContext = [...$event->data, ...$context->data];

        return new LogEntry(
            level: $event->logLevel,
            message: $message,
            context: $mergedContext,
            timestamp: $event->createdAt,
        );
    }
}

/**
 * Template-based formatter - uses message templates
 */
final readonly class TemplateFormatter implements EventFormatter
{
    /**
     * @param array<class-string, string> $templates
     */
    public function __construct(
        private array $templates,
        private string $defaultTemplate = '{event}',
    ) {}

    public function __invoke(Event $event, LogContext $context): LogEntry
    {
        $template = $this->templates[$event::class] ?? $this->defaultTemplate;
        $message = $this->interpolate($template, $event, $context);

        return new LogEntry(
            level: $event->logLevel,
            message: $message,
            context: [...$event->data, ...$context->data],
            timestamp: $event->createdAt,
        );
    }

    private function interpolate(string $template, Event $event, LogContext $context): string
    {
        $vars = [
            'event' => $event->name(),
            ...$event->data,
            ...$context->data,
        ];

        return preg_replace_callback(
            '/\{(\w+)\}/',
            fn($m) => $vars[$m[1]] ?? $m[0],
            $template
        );
    }
}
```

### 6. Writer Implementations

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging\Writers;

use Cognesy\Instructor\Logging\Pipeline\LogWriter;
use Cognesy\Instructor\Logging\LogEntry;
use Psr\Log\LoggerInterface;

/**
 * Writes to PSR-3 logger
 */
final readonly class PsrLogWriter implements LogWriter
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LogEntry $entry): void
    {
        $this->logger->log($entry->level, $entry->message, $entry->context);
    }
}

/**
 * Writes to multiple writers
 */
final readonly class MultiWriter implements LogWriter
{
    /**
     * @param array<LogWriter> $writers
     */
    public function __construct(
        private array $writers,
    ) {}

    public function __invoke(LogEntry $entry): void
    {
        foreach ($this->writers as $writer) {
            $writer($entry);
        }
    }
}

/**
 * Buffered writer - collects entries and flushes in batches
 */
final class BufferedWriter implements LogWriter
{
    private array $buffer = [];

    public function __construct(
        private LogWriter $innerWriter,
        private int $bufferSize = 100,
    ) {}

    public function __invoke(LogEntry $entry): void
    {
        $this->buffer[] = $entry;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        foreach ($this->buffer as $entry) {
            ($this->innerWriter)($entry);
        }
        $this->buffer = [];
    }

    public function __destruct()
    {
        $this->flush();
    }
}
```

### 7. Pipeline Composition

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging;

use Cognesy\Events\Event;
use Cognesy\Instructor\Logging\Pipeline\{EventFilter, EventEnricher, EventFormatter, LogWriter};

/**
 * Composable event logging pipeline
 *
 * Implements: Event → Option<void>
 */
final readonly class EventLoggingPipeline
{
    public function __construct(
        private EventFilter $filter,
        private EventEnricher $enricher,
        private EventFormatter $formatter,
        private LogWriter $writer,
    ) {}

    /**
     * Process event through pipeline
     * Returns true if logged, false if filtered out
     */
    public function __invoke(Event $event): bool
    {
        // Filter stage - short-circuit if None
        $filtered = ($this->filter)($event);
        if ($filtered->isNone()) {
            return false;
        }

        // Enrich stage
        [$event, $context] = ($this->enricher)($event);

        // Format stage
        $entry = ($this->formatter)($event, $context);

        // Write stage (side effect)
        ($this->writer)($entry);

        return true;
    }
}
```

### 8. Builder for Fluent Construction

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging;

use Cognesy\Instructor\Logging\Pipeline\{EventFilter, EventEnricher, EventFormatter, LogWriter};
use Cognesy\Instructor\Logging\Filters\{LogLevelFilter, CompositeFilter};
use Cognesy\Instructor\Logging\Enrichers\{EventMetadataEnricher, CompositeEnricher, StaticContextEnricher};
use Cognesy\Instructor\Logging\Formatters\StandardFormatter;
use Cognesy\Instructor\Logging\Writers\PsrLogWriter;
use Psr\Log\LoggerInterface;

/**
 * Fluent builder for logging pipelines
 */
final class EventLoggingPipelineBuilder
{
    private array $filters = [];
    private array $enrichers = [];
    private ?EventFormatter $formatter = null;
    private ?LogWriter $writer = null;

    public static function create(): self
    {
        return new self();
    }

    public function filterByLevel(LogThreshold $threshold): self
    {
        $this->filters[] = new LogLevelFilter($threshold);
        return $this;
    }

    public function addFilter(EventFilter $filter): self
    {
        $this->filters[] = $filter;
        return $this;
    }

    public function withStaticContext(array $context): self
    {
        $this->enrichers[] = new StaticContextEnricher(LogContext::from($context));
        return $this;
    }

    public function withDynamicContext(callable $provider): self
    {
        $this->enrichers[] = new LazyContextEnricher($provider);
        return $this;
    }

    public function addEnricher(EventEnricher $enricher): self
    {
        $this->enrichers[] = $enricher;
        return $this;
    }

    public function withFormatter(EventFormatter $formatter): self
    {
        $this->formatter = $formatter;
        return $this;
    }

    public function withWriter(LogWriter $writer): self
    {
        $this->writer = $writer;
        return $this;
    }

    public function toPsrLogger(LoggerInterface $logger): self
    {
        return $this->withWriter(new PsrLogWriter($logger));
    }

    public function build(): EventLoggingPipeline
    {
        // Always include metadata enricher
        $enrichers = [new EventMetadataEnricher(), ...$this->enrichers];

        return new EventLoggingPipeline(
            filter: count($this->filters) > 1
                ? new CompositeFilter($this->filters)
                : ($this->filters[0] ?? new class implements EventFilter {
                    public function __invoke($event) { return \Cognesy\Utils\Result\Option::some($event); }
                }),
            enricher: new CompositeEnricher($enrichers),
            formatter: $this->formatter ?? new StandardFormatter(),
            writer: $this->writer ?? throw new \LogicException('Writer must be configured'),
        );
    }
}
```

### 9. Framework Integration Factories

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Logging\Integration;

use Cognesy\Instructor\Logging\EventLoggingPipelineBuilder;
use Cognesy\Instructor\Logging\LogThreshold;
use Psr\Log\LoggerInterface;

/**
 * Framework-agnostic factory for logging pipelines
 */
final class LoggingListenerFactory
{
    /**
     * Create standalone listener with PSR-3 logger
     */
    public static function standalone(
        LoggerInterface $logger,
        LogThreshold $threshold = LogThreshold::DEBUG,
        array $staticContext = [],
    ): callable {
        $pipeline = EventLoggingPipelineBuilder::create()
            ->filterByLevel($threshold)
            ->withStaticContext($staticContext)
            ->toPsrLogger($logger)
            ->build();

        return fn($event) => $pipeline($event);
    }

    /**
     * Create Laravel-integrated listener
     *
     * Automatically adds Laravel request context via lazy evaluation
     */
    public static function forLaravel(
        LoggerInterface $logger,
        array $config = [],
    ): callable {
        $threshold = LogThreshold::from($config['level'] ?? 'debug');

        $pipeline = EventLoggingPipelineBuilder::create()
            ->filterByLevel($threshold)
            ->withDynamicContext(fn() => static::laravelContext())
            ->toPsrLogger($logger)
            ->build();

        return fn($event) => $pipeline($event);
    }

    /**
     * Create Symfony-integrated listener
     */
    public static function forSymfony(
        LoggerInterface $logger,
        array $config = [],
    ): callable {
        $threshold = LogThreshold::from($config['level'] ?? 'debug');

        $pipeline = EventLoggingPipelineBuilder::create()
            ->filterByLevel($threshold)
            ->withDynamicContext(fn() => static::symfonyContext())
            ->toPsrLogger($logger)
            ->build();

        return fn($event) => $pipeline($event);
    }

    private static function laravelContext(): array
    {
        if (!function_exists('request')) {
            return [];
        }

        $request = request();
        return [
            'request_id' => $request->header('X-Request-ID') ?? uniqid(),
            'user_id' => optional($request->user())->id,
            'route' => optional($request->route())->getName(),
        ];
    }

    private static function symfonyContext(): array
    {
        // Symfony context extraction via RequestStack
        return [];
    }
}
```

## Usage Examples

### Standalone Usage

```php
<?php

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Logging\Integration\LoggingListenerFactory;
use Cognesy\Instructor\Logging\LogThreshold;
use Psr\Log\LoggerInterface;

// Create logger
$logger = new FileLogger('logs/instructor.log');

// Create logging listener
$loggingListener = LoggingListenerFactory::standalone(
    logger: $logger,
    threshold: LogThreshold::INFO,
    staticContext: ['app' => 'my-app', 'env' => 'production'],
);

// Use with domain class - domain class remains unchanged
$user = (new StructuredOutput)
    ->wiretap($loggingListener)  // Single wiretap call
    ->withMessages("Jason is 25 years old")
    ->withResponseClass(User::class)
    ->get();
```

### Laravel Integration

```php
<?php

// config/instructor.php
return [
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'channel' => 'instructor',
    ],
];

// app/Providers/AppServiceProvider.php
use Cognesy\Instructor\Logging\Integration\LoggingListenerFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!config('instructor.logging.enabled')) {
            return;
        }

        // Create logging listener
        $logger = Log::channel(config('instructor.logging.channel'));
        $loggingListener = LoggingListenerFactory::forLaravel(
            logger: $logger,
            config: config('instructor.logging'),
        );

        // Register as global wiretap - applies to all instances
        $this->app->resolving(StructuredOutput::class, function($instance) use ($loggingListener) {
            $instance->wiretap($loggingListener);
        });
    }
}

// Usage in controller - completely transparent
class UserController extends Controller
{
    public function extract(StructuredOutput $output): JsonResponse
    {
        // Logging happens automatically via wiretap
        $user = $output
            ->withMessages($request->input('text'))
            ->withResponseClass(User::class)
            ->get();

        return response()->json($user);
    }
}
```

### Advanced Composition

```php
<?php

use Cognesy\Instructor\Logging\EventLoggingPipelineBuilder;
use Cognesy\Instructor\Logging\LogThreshold;
use Cognesy\Instructor\Logging\Filters\SamplingFilter;
use Cognesy\Instructor\Logging\Writers\{PsrLogWriter, MultiWriter, BufferedWriter};

// Complex pipeline with sampling, buffering, and multiple outputs
$pipeline = EventLoggingPipelineBuilder::create()
    ->filterByLevel(LogThreshold::INFO)
    ->addFilter(new SamplingFilter(0.1))  // Sample 10% of events
    ->withStaticContext(['datacenter' => 'us-east-1'])
    ->withDynamicContext(fn() => ['memory' => memory_get_usage()])
    ->withWriter(new BufferedWriter(
        new MultiWriter([
            new PsrLogWriter($fileLogger),
            new PsrLogWriter($cloudLogger),
        ]),
        bufferSize: 500
    ))
    ->build();

$listener = fn($event) => $pipeline($event);
```

## Migration Path

### Phase 1: Introduce Logging Pipeline (No Breaking Changes)

1. Add `packages/instructor-logging/` package with pipeline components
2. All domain classes remain unchanged
3. Update documentation with new logging patterns
4. Mark old `Event::print()` methods as deprecated

### Phase 2: Framework Integration Packages

1. Create `packages/instructor-logging-laravel/` with Laravel-specific factories
2. Create `packages/instructor-logging-symfony/` with Symfony-specific factories
3. Provide ServiceProvider that auto-registers wiretaps
4. No changes to existing ServiceProviders required

### Phase 3: Deprecation

1. Deprecate direct logging methods on Event class
2. Provide migration guide from old patterns to new
3. Remove deprecated methods in next major version

## Benefits Over Current Proposal

### 1. Zero Domain Pollution

Domain classes (`StructuredOutput`, `Inference`, `Embeddings`) remain completely unchanged. No logging interfaces, no logging state, no logging methods.

### 2. Functional Composition

Every stage of the pipeline is a pure function. Easy to test, easy to reason about, easy to compose.

```php
// Test filter independently
$filter = new LogLevelFilter(LogThreshold::INFO);
$result = $filter($debugEvent);
assert($result->isNone());

// Test enricher independently
$enricher = new StaticContextEnricher(LogContext::from(['app' => 'test']));
[$event, $context] = $enricher($event);
assert($context->data['app'] === 'test');
```

### 3. Framework Integration Without Coupling

Framework-specific logic is isolated to factories that produce closures. No framework dependencies in core logging package.

```php
// Laravel package provides factory
$listener = LoggingListenerFactory::forLaravel($logger);

// Core domain code just accepts a closure
$output->wiretap($listener);
```

### 4. Performance Through Lazy Evaluation

Context providers only execute when event passes filtering:

```php
$pipeline = EventLoggingPipelineBuilder::create()
    ->filterByLevel(LogThreshold::ERROR)  // Most events filtered here
    ->withDynamicContext($expensiveProvider)  // Only called for ERROR+ events
    ->build();
```

### 5. Type Safety

All pipeline stages have clear type signatures:

```php
EventFilter:    Event → Option<Event>
EventEnricher:  Event → (Event, LogContext)
EventFormatter: (Event, LogContext) → LogEntry
LogWriter:      LogEntry → void
```

### 6. Testability

Every component is independently testable without framework dependencies:

```php
test('filters events below threshold', function() {
    $filter = new LogLevelFilter(LogThreshold::WARNING);
    $debugEvent = new Event(['level' => 'debug']);

    $result = $filter($debugEvent);

    expect($result->isNone())->toBeTrue();
});
```

### 7. Extensibility

New filters, enrichers, formatters, and writers can be added without modifying existing code:

```php
// Add custom filter
class BusinessHoursFilter implements EventFilter {
    public function __invoke(Event $event): Option {
        return (date('H') >= 9 && date('H') < 17)
            ? Option::some($event)
            : Option::none();
    }
}

// Use in pipeline
$pipeline = EventLoggingPipelineBuilder::create()
    ->addFilter(new BusinessHoursFilter())
    ->build();
```

## Comparison Table

| Aspect | Current Proposal | Functional Pipeline |
|--------|------------------|---------------------|
| **Domain Layer** | Polluted with logging interfaces | Clean - unchanged |
| **Framework Integration** | ServiceProvider per framework | Factory function per framework |
| **Testability** | Requires mocking framework | Pure functions - no mocking |
| **Composition** | Stateful objects | Pure function composition |
| **Performance** | Eager evaluation of context | Lazy evaluation with filtering |
| **Type Safety** | Array-based context | Strongly typed pipeline stages |
| **Extensibility** | Modify service providers | Add new implementations of interfaces |
| **Lines of Code** | ~2000 (proposal) | ~800 (pipeline) |
| **Dependencies** | Framework-specific | PSR-3 only |

## Conclusion

The current proposal's architectural flaws stem from:

1. Mixing domain concerns with infrastructure concerns
2. Object-oriented bias over functional composition
3. Framework integration at the wrong architectural layer
4. Lack of clear type boundaries

The functional pipeline approach:

1. Preserves domain layer purity
2. Leverages PHP 8.2+ type system for safety
3. Provides superior testability through pure functions
4. Scales naturally to new frameworks without code duplication
5. Maintains performance through lazy evaluation
6. Aligns with DDD principles - infrastructure stays in infrastructure layer

**Recommendation**: Abandon the LoggerAware/HandlesLogging approach. Implement the functional event logging pipeline as a separate package that integrates via the existing event system's wiretap mechanism.
