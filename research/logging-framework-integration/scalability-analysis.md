# Why the Framework Logging Integration Proposal Won't Scale

## The Fundamental Problem

The proposed approach violates core architectural principles and creates a maintenance nightmare. Here's why:

## 1. Domain Layer Pollution

### Problem
Adding `LoggerAware` interface and `HandlesLogging` trait to domain classes (`StructuredOutput`, `Inference`, `Embeddings`) violates the **Single Responsibility Principle** and **Separation of Concerns**.

```php
// WRONG: Domain class becomes responsible for logging configuration
class StructuredOutput implements LoggerAware
{
    private LoggerInterface $logger;
    private bool $loggingEnabled = false;
    private string $logLevel = LogLevel::DEBUG;
    private array $logContext = [];
    private bool $metricsEnabled = false;

    // 11 new methods just for logging!
    public function withLogging(bool $enabled = true): static { /* ... */ }
    public function withLogLevel(string $level): static { /* ... */ }
    public function withLogContext(array $context): static { /* ... */ }
    // ... 8 more logging methods
}
```

### Why This Fails
- **Domain classes should focus on business logic**, not infrastructure concerns
- **State explosion**: Each instance carries logging configuration state
- **Testing complexity**: Must mock loggers to test business logic
- **Tight coupling**: Domain layer depends on PSR-3 Logger interface

## 2. Framework ServiceProvider Explosion

### Problem
Each framework requires duplicated manual wiring code that must be updated every time we add a new domain class.

```php
// Laravel ServiceProvider
$this->app->bind(StructuredOutput::class, function ($app) {
    $instance = new StructuredOutput(/*...*/);
    $this->configureLogging($instance, $app);  // Manual wiring
    return $instance;
});

$this->app->bind(Inference::class, function ($app) {
    $instance = new Inference(/*...*/);
    $this->configureLogging($instance, $app);  // Duplicated logic
    return $instance;
});

// Repeated for EVERY domain class...
```

### Scaling Problems
- **O(n×m) growth**: n domain classes × m frameworks = exponential ServiceProvider code
- **Maintenance burden**: Adding `Transcription` class requires updating 2+ ServiceProviders
- **Configuration drift**: Framework-specific logging configs get out of sync
- **No composition**: Can't reuse logging setup across frameworks

## 3. Stateful Services Anti-Pattern

### Problem
`EventLogConverter` mixes configuration with behavior, preventing composition and lazy evaluation.

```php
class EventLogConverter
{
    public function __construct(
        private LoggerInterface $logger,           // Dependency
        private array $contextProviders = [],     // Configuration
        private string $minimumLevel = LogLevel::DEBUG, // Configuration
        private bool $includeMetrics = true,      // Configuration
    ) {}
}
```

### Why This Is Bad
- **Not composable**: Can't mix different filters/enrichers per event type
- **Eager evaluation**: Context providers always execute, even when events are filtered
- **Configuration hell**: Mix of constructor args, method calls, and config files
- **Poor testability**: Requires mocking entire object graph

## 4. Context Provider Inefficiency

### Problem
Framework-specific context is computed for every event, even when filtered out.

```php
public function getContext(): array
{
    // This ALWAYS executes, even if event will be discarded
    return [
        'request_id' => $this->request->header('X-Request-ID') ?? uniqid(),
        'user_id' => optional($this->request->user())->id,
        'session_id' => $this->request->session()?->getId(),
        // ... expensive operations
    ];
}
```

### Performance Impact
- **CPU waste**: Computing context for discarded DEBUG events in production
- **Memory pressure**: Creating arrays for every event
- **Database queries**: User/session lookups happen even when not needed

## 5. Configuration Precedence Chaos

### Problem
Configuration scattered across multiple places with unclear precedence:

1. Constructor parameters
2. Method calls (`withLogLevel()`)
3. Config files (`config/instructor.php`)
4. Environment variables
5. Framework defaults

### Example Confusion
```php
// Which wins?
$output = (new StructuredOutput)
    ->withLogLevel('error')        // Method call
    ->withLogging(true);           // Method call

// vs config file:
'logging' => ['level' => 'debug']  // Config file

// vs environment:
INSTRUCTOR_LOG_LEVEL=info          // Environment
```

## A Better Approach: Functional Event Pipeline

### Core Principle
**Logging is infrastructure, not domain concern.** Domain classes should be pure business logic.

### Architecture

```
Domain Classes (unchanged)
    ↓ emit events via existing HandlesEvents
Event System (existing wiretap pattern)
    ↓
Logging Pipeline (new - pure functions)
    Filter → Enrich → Format → Write
    ↓
PSR-3 Logger
```

### Implementation

#### 1. Immutable Value Objects

```php
readonly class LogEntry
{
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
        public DateTimeImmutable $timestamp,
    ) {}
}

readonly class LogContext
{
    public function __construct(
        public string $eventId,
        public string $eventClass,
        public array $eventData,
        public array $frameworkContext = [],
    ) {}
}
```

#### 2. Pure Function Pipeline Stages

```php
interface EventFilter
{
    public function __invoke(Event $event): bool;
}

interface EventEnricher
{
    public function __invoke(Event $event): LogContext;
}

interface EventFormatter
{
    public function __invoke(Event $event, LogContext $context): LogEntry;
}

interface LogWriter
{
    public function __invoke(LogEntry $entry): void;
}
```

#### 3. Composable Implementations

```php
// Filters compose like Lego blocks
final readonly class LogLevelFilter implements EventFilter
{
    public function __construct(private LogLevel $minimumLevel) {}

    public function __invoke(Event $event): bool
    {
        return LogLevel::rank($event->logLevel) <= LogLevel::rank($this->minimumLevel);
    }
}

final readonly class EventClassFilter implements EventFilter
{
    public function __construct(private array $excludedClasses) {}

    public function __invoke(Event $event): bool
    {
        return !in_array($event::class, $this->excludedClasses, true);
    }
}

// Combine multiple filters
final readonly class CompositeFilter implements EventFilter
{
    public function __construct(private array $filters) {}

    public function __invoke(Event $event): bool
    {
        return array_reduce(
            $this->filters,
            fn(bool $keep, EventFilter $filter) => $keep && $filter($event),
            true
        );
    }
}
```

#### 4. Framework Integration via Factories

```php
final class LaravelLoggingFactory
{
    public static function create(Application $app): callable
    {
        return LoggingPipeline::create()
            ->filter(new LogLevelFilter(config('instructor.log_level', 'debug')))
            ->filter(new EventClassFilter(config('instructor.exclude_events', [])))
            ->enrich(new LazyEnricher(fn() => [
                'request_id' => $app['request']->header('X-Request-ID'),
                'user_id' => optional($app['request']->user())->id,
            ]))
            ->format(new MessageFormatter(config('instructor.formatters', [])))
            ->write(new LoggerWriter($app['log']->channel(config('instructor.channel'))))
            ->build();
    }
}

// Usage in ServiceProvider
public function boot(): void
{
    if (config('instructor.logging.enabled')) {
        $pipeline = LaravelLoggingFactory::create($this->app);

        // Apply to ALL domain classes automatically
        $this->app->afterResolving(HandlesEvents::class, function($instance) use ($pipeline) {
            $instance->wiretap($pipeline);
        });
    }
}
```

### Benefits of Functional Approach

#### 1. Zero Domain Pollution
```php
// Domain classes remain pure - ZERO logging code
class StructuredOutput
{
    // Only business logic, no logging concerns
    public function get(): object { /* pure business logic */ }
}
```

#### 2. Composable and Reusable
```php
// Mix and match components
$pipeline = LoggingPipeline::create()
    ->filter($levelFilter)
    ->filter($classFilter)
    ->filter($samplingFilter)
    ->enrich($requestEnricher)
    ->enrich($userEnricher)
    ->format($customFormatter)
    ->write($fileWriter)
    ->write($elkWriter);  // Multiple outputs!
```

#### 3. Lazy Evaluation
```php
final readonly class LazyEnricher implements EventEnricher
{
    public function __construct(private \Closure $provider) {}

    public function __invoke(Event $event): LogContext
    {
        // Only evaluate if event passes filters
        $context = ($this->provider)();
        return new LogContext(/* ... */, $context);
    }
}
```

#### 4. Framework Scaling
```php
// Adding Symfony: 20 lines total
final class SymfonyLoggingFactory
{
    public static function create(ContainerInterface $container): callable
    {
        return LoggingPipeline::create()
            ->filter(new LogLevelFilter($container->getParameter('instructor.log_level')))
            ->enrich(new LazyEnricher(fn() => [
                'request_id' => $container->get('request_stack')->getCurrentRequest()?->headers->get('X-Request-ID'),
            ]))
            ->write(new LoggerWriter($container->get('logger')))
            ->build();
    }
}
```

#### 5. Pure Function Testing
```php
class LogLevelFilterTest extends TestCase
{
    public function test_filters_by_level(): void
    {
        $filter = new LogLevelFilter(LogLevel::WARNING);
        $debugEvent = new SomeEvent(logLevel: LogLevel::DEBUG);
        $errorEvent = new SomeEvent(logLevel: LogLevel::ERROR);

        $this->assertFalse($filter($debugEvent));
        $this->assertTrue($filter($errorEvent));
    }
}
// No mocking required!
```

## Conclusion

The original proposal fails because it:

1. **Pollutes domain layer** with infrastructure concerns
2. **Doesn't compose** - requires manual wiring per class/framework
3. **Performs poorly** - eager evaluation and state overhead
4. **Doesn't scale** - O(n×m) ServiceProvider explosion

The functional pipeline approach:

1. **Preserves domain purity** - zero changes to business logic
2. **Composes naturally** - Lego-block architecture
3. **Performs optimally** - lazy evaluation and pure functions
4. **Scales infinitely** - new framework = 20 lines, new domain class = 0 lines

**Recommendation**: Build `packages/instructor-logging/` as a separate functional pipeline package that integrates via the existing event system's wiretap mechanism.