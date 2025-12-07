# Why the Current Logging Proposal Won't Scale: TL;DR

## The Fatal Flaw

The proposal adds `LoggerAware` interfaces and logging state directly to domain classes:

```php
// WRONG: Domain class polluted with infrastructure concerns
class StructuredOutput implements LoggerAware
{
    use HandlesLogging;
    private LoggerInterface $logger;
    private bool $loggingEnabled = false;
    private string $logLevel = LogLevel::DEBUG;
    // ... domain logic mixed with logging configuration
}
```

**Why this fails**: Every domain class becomes responsible for logging configuration AND business logic. Violates Single Responsibility Principle and Domain-Driven Design.

## The Scaling Problem

Each framework needs custom ServiceProvider with manual wiring:

```php
// WRONG: Framework-specific boilerplate that duplicates everywhere
class InstructorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StructuredOutput::class, function ($app) {
            $instance = new StructuredOutput(/* ... */);
            $this->configureLogging($instance, $app);  // Manual wiring
            return $instance;
        });
        // Repeat for Inference, Embeddings, etc.
    }
}
```

**Why this fails**:
- Each new framework = new ServiceProvider
- Each new domain class = update every ServiceProvider
- No separation between registration and configuration
- Cannot test without entire framework

## The Right Architecture

**Key Insight**: The event system IS the logging abstraction. We already have:

1. Structured events with log levels
2. PSR-14 dispatcher with wiretap pattern
3. Framework adapters (LaravelEventDispatcher, SymfonyEventDispatcher)

**What's missing**: A composable, functional event listener that doesn't pollute domain objects.

## The Solution: Functional Pipeline

```php
// Build logging pipeline from composable functions
$loggingListener = EventLoggingPipelineBuilder::create()
    ->filterByLevel(LogThreshold::INFO)
    ->withDynamicContext(fn() => laravelRequestContext())
    ->toPsrLogger($logger)
    ->build();

// Use with domain class - domain class unchanged
$user = (new StructuredOutput)
    ->wiretap($loggingListener)  // Single line integration
    ->withMessages("Jason is 25 years old")
    ->withResponseClass(User::class)
    ->get();
```

## Architecture Overview

```
Domain Layer (unchanged)
    ↓ emits events
Event System (existing)
    ↓ wiretap
Logging Pipeline (NEW - pure functions)
    Filter → Enrich → Format → Log
    ↓
PSR-3 Logger
```

## Core Components

### 1. Immutable Value Objects

```php
final readonly class LogEntry {
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
        public DateTimeImmutable $timestamp,
    ) {}
}
```

### 2. Pipeline Stages (Pure Functions)

```php
interface EventFilter {
    public function __invoke(Event $event): Option;  // Event → Option<Event>
}

interface EventEnricher {
    public function __invoke(Event $event): array;  // Event → (Event, Context)
}

interface EventFormatter {
    public function __invoke(Event $event, LogContext $context): LogEntry;
}

interface LogWriter {
    public function __invoke(LogEntry $entry): void;  // Side effect
}
```

### 3. Composable Implementations

```php
// Compose filters
new CompositeFilter([
    new LogLevelFilter(LogThreshold::INFO),
    new SamplingFilter(0.1),  // Log 10% of events
]);

// Compose enrichers
new CompositeEnricher([
    new EventMetadataEnricher(),
    new LazyContextEnricher(fn() => requestContext()),
]);
```

### 4. Framework Integration via Factories

```php
// Laravel integration - just a factory function
final class LoggingListenerFactory
{
    public static function forLaravel(
        LoggerInterface $logger,
        array $config = [],
    ): callable {
        return EventLoggingPipelineBuilder::create()
            ->filterByLevel(LogThreshold::from($config['level'] ?? 'debug'))
            ->withDynamicContext(fn() => static::laravelContext())
            ->toPsrLogger($logger)
            ->build();
    }

    private static function laravelContext(): array {
        $request = request();
        return [
            'request_id' => $request->header('X-Request-ID'),
            'user_id' => optional($request->user())->id,
        ];
    }
}
```

## Laravel Integration Example

```php
// app/Providers/AppServiceProvider.php
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $loggingListener = LoggingListenerFactory::forLaravel(
            logger: Log::channel('instructor'),
            config: config('instructor.logging'),
        );

        // Auto-wire to all instances
        $this->app->resolving(StructuredOutput::class, function($instance) use ($loggingListener) {
            $instance->wiretap($loggingListener);
        });
    }
}

// Controller - completely transparent
class UserController extends Controller
{
    public function extract(StructuredOutput $output): JsonResponse
    {
        // Logging happens automatically - no domain pollution
        $user = $output
            ->withMessages($request->input('text'))
            ->withResponseClass(User::class)
            ->get();

        return response()->json($user);
    }
}
```

## Key Benefits

### 1. Zero Domain Pollution
Domain classes remain unchanged. No logging interfaces, no state, no configuration.

### 2. Functional Composition
Every stage is a pure function. Easy to test, reason about, compose.

```php
// Test independently - no mocking
test('filters debug events', function() {
    $filter = new LogLevelFilter(LogThreshold::INFO);
    $result = $filter(new DebugEvent());
    expect($result->isNone())->toBeTrue();
});
```

### 3. Framework Agnostic Core
Core logging package has zero framework dependencies. Framework-specific code isolated to factory functions.

### 4. Performance Through Lazy Evaluation
Context providers only execute when event passes filtering:

```php
EventLoggingPipelineBuilder::create()
    ->filterByLevel(LogThreshold::ERROR)  // Most events stop here
    ->withDynamicContext($expensiveProvider)  // Only called for ERROR+ events
    ->build();
```

### 5. Type Safety
Clear type signatures for every stage:

```php
Filter:     Event → Option<Event>
Enrich:     Event → (Event, LogContext)
Format:     (Event, LogContext) → LogEntry
Write:      LogEntry → void
```

### 6. Extensibility Without Modification
New filters/enrichers/formatters just implement interface:

```php
class BusinessHoursFilter implements EventFilter {
    public function __invoke(Event $event): Option {
        return isBusinessHours() ? Option::some($event) : Option::none();
    }
}

// Use immediately
$pipeline->addFilter(new BusinessHoursFilter());
```

## Comparison

| Aspect | Current Proposal | Functional Pipeline |
|--------|------------------|---------------------|
| **Domain Classes** | Polluted with LoggerAware | Unchanged |
| **Framework Code** | ServiceProvider per framework | Factory per framework |
| **Testing** | Requires framework mocking | Pure functions |
| **Composition** | Stateful objects | Function composition |
| **Performance** | Eager context evaluation | Lazy with filtering |
| **Type Safety** | Array context | Strong types |
| **LOC** | ~2000 lines | ~800 lines |

## Migration Path

### Phase 1: Add Pipeline Package
- New `instructor-logging` package
- Domain classes unchanged
- Zero breaking changes

### Phase 2: Framework Packages
- `instructor-logging-laravel` with factory
- `instructor-logging-symfony` with factory
- Auto-register via ServiceProvider

### Phase 3: Deprecation
- Deprecate `Event::print()` methods
- Provide migration guide
- Remove in next major

## The Core Principle

**Logging is an infrastructure concern, not a domain concern.**

The current proposal violates this by mixing:
- Domain logic (what to compute)
- Infrastructure logic (how to observe)

The functional pipeline approach maintains clean separation:
- Domain layer: Emit events, no logging knowledge
- Infrastructure layer: Build logging pipeline, attach via wiretap
- Framework layer: Provide context via factory functions

## Recommendation

**Do not implement the LoggerAware/HandlesLogging approach.**

Instead:
1. Build functional event logging pipeline as separate package
2. Integrate via existing event system's wiretap mechanism
3. Provide framework-specific factories for context injection
4. Keep domain layer pure and testable

This approach scales infinitely - adding a new framework is just writing a factory function. Adding a new domain class requires zero logging code.

---

**Full analysis**: See `logging-architecture-analysis.md` for complete implementation details and code examples.
