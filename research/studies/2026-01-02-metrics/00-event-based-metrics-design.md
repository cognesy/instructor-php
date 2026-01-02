# Event-Based Metrics Collection System - Design Study

## Problem Statement

The current implementation of inference stats in Polyglot has several issues:

1. **Tight coupling** - Stats calculation logic is embedded directly in `PendingInference` and `InferenceStream`
2. **Single concern violation** - Core inference classes handle both inference AND metrics
3. **Not composable** - Can't easily add/remove/replace metrics collection
4. **Not extensible** - Adding new metrics requires modifying core classes

We need a **generic, event-driven metrics collection system** that:
- Decouples metrics from core functionality
- Supports multiple independent collectors
- Is opt-in and composable
- Can be used across the entire InstructorPHP ecosystem

---

## Industry Best Practices Research

### 1. OpenTelemetry (CNCF Standard)

**Architecture:**
```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Application │───▶│   SDK/API    │───▶│   Exporter   │───▶ Backend
│   (Events)   │    │  (Meters,    │    │ (Prometheus, │
│              │    │   Spans)     │    │  OTLP, etc)  │
└──────────────┘    └──────────────┘    └──────────────┘
```

**Key Concepts:**
- **Meter** - Factory for creating instruments
- **Instruments** - Counter, UpDownCounter, Histogram, Gauge
- **Attributes/Labels** - Dimensional metadata (key-value pairs)
- **MeterProvider** - Central registry for meters
- **MetricReader** - Collects metrics on interval or on-demand
- **MetricExporter** - Sends to backends (Prometheus, OTLP, etc.)

**PHP SDK Example:**
```php
$meter = $meterProvider->getMeter('inference');
$counter = $meter->createCounter('inference.requests');
$histogram = $meter->createHistogram('inference.duration');

$counter->add(1, ['model' => 'gpt-4', 'status' => 'success']);
$histogram->record(1234.5, ['model' => 'gpt-4']);
```

**Strengths:**
- Industry standard, vendor-neutral
- Rich ecosystem of exporters
- Semantic conventions for common metrics

**Weaknesses:**
- Complex SDK, heavy dependency
- Overkill for simple use cases

---

### 2. Prometheus Client Libraries

**Architecture:**
```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Application │───▶│  Collectors  │───▶│   Registry   │───▶ /metrics
│   (Events)   │    │  (Counter,   │    │  (renders    │     endpoint
│              │    │   Gauge...)  │    │   text/prom) │
└──────────────┘    └──────────────┘    └──────────────┘
```

**Key Concepts:**
- **Collector** - Interface that collects metrics (can be custom)
- **Registry** - Central place to register collectors
- **Metric Types** - Counter, Gauge, Histogram, Summary
- **Labels** - Dimensional metadata
- **CollectorRegistry::getDefault()** - Global registry pattern

**PHP Client Example (promphp/prometheus_client_php):**
```php
$registry = new CollectorRegistry(new InMemory());

$counter = $registry->getOrRegisterCounter(
    'app', 'requests_total', 'Total requests', ['method', 'status']
);
$counter->incBy(1, ['POST', '200']);

$histogram = $registry->getOrRegisterHistogram(
    'app', 'request_duration_seconds', 'Request duration',
    ['method'], [0.1, 0.5, 1, 2, 5]
);
$histogram->observe(0.345, ['POST']);
```

**Strengths:**
- Simple, well-understood model
- Lightweight
- Built-in histogram buckets

**Weaknesses:**
- Pull-based model (requires /metrics endpoint)
- No built-in event integration

---

### 3. StatsD / Datadog DogStatsD

**Architecture:**
```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Application │───▶│   Client     │───▶│   Agent      │───▶ Backend
│              │    │  (UDP push)  │    │  (aggregates)│
└──────────────┘    └──────────────┘    └──────────────┘
```

**Key Concepts:**
- **Fire-and-forget** - UDP packets, no blocking
- **Metric Types** - Counter, Gauge, Histogram, Timer, Set
- **Tags** - Key-value metadata
- **Sampling** - Reduce volume with statistical sampling

**Example:**
```php
$client->increment('inference.requests', 1, ['model:gpt-4']);
$client->timing('inference.duration', 1234, ['model:gpt-4']);
$client->histogram('inference.tokens', 500, ['type:output']);
```

**Strengths:**
- Very lightweight, non-blocking
- Simple API
- Agent handles aggregation

**Weaknesses:**
- Requires external agent
- UDP can lose packets

---

### 4. Micrometer (Java) - Metrics Facade

**Architecture:**
```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Application │───▶│  Micrometer  │───▶│   Registry   │───▶ Multiple
│   (Meters)   │    │   Facade     │    │  (Composite) │     Backends
└──────────────┘    └──────────────┘    └──────────────┘
```

**Key Concepts:**
- **MeterRegistry** - Central registry, can be composite
- **Meter** - Named metric with tags
- **Binders** - Auto-collect JVM metrics, etc.
- **Composite Registry** - Send to multiple backends

**Example:**
```java
Counter counter = registry.counter("requests", "method", "GET");
counter.increment();

Timer timer = registry.timer("requests.duration");
timer.record(Duration.ofMillis(100));

// Functional style
Timer.Sample sample = Timer.start(registry);
// ... do work ...
sample.stop(timer);
```

**Strengths:**
- Clean facade over multiple backends
- Dimensional metrics first-class
- Timer.Sample pattern for measuring operations

**Weaknesses:**
- Java-specific patterns

---

### 5. Symfony Stopwatch

**Architecture:**
```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Application │───▶│  Stopwatch   │───▶│   Events     │───▶ Profiler
│  start/stop  │    │  (Sections,  │    │  (duration,  │
│              │    │   Periods)   │    │   memory)    │
└──────────────┘    └──────────────┘    └──────────────┘
```

**Key Concepts:**
- **Sections** - Group related measurements
- **Events** - Named timing points with laps
- **Periods** - Individual start/stop intervals

**Example:**
```php
$stopwatch = new Stopwatch();

$stopwatch->start('inference');
// ... do inference ...
$event = $stopwatch->stop('inference');

echo $event->getDuration(); // ms
echo $event->getMemory();   // bytes
```

**Strengths:**
- Simple API
- Memory tracking built-in
- Sections for grouping

**Weaknesses:**
- Timing only, no counters/gauges
- No export mechanism

---

### 6. Laravel Telescope (Event-Driven)

**Architecture:**
```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Application │───▶│   Watchers   │───▶│   Storage    │───▶ Dashboard
│   (Events)   │    │  (subscribe  │    │  (DB, Redis) │
│              │    │   to events) │    │              │
└──────────────┘    └──────────────┘    └──────────────┘
```

**Key Concepts:**
- **Watchers** - Subscribe to specific event types
- **Entries** - Recorded data points
- **Tags** - For filtering/searching
- **Filtering** - Conditionally record based on request

**Example Watcher Pattern:**
```php
class QueryWatcher extends Watcher {
    public function register($app) {
        $app['events']->listen(QueryExecuted::class, [$this, 'recordQuery']);
    }

    public function recordQuery(QueryExecuted $event) {
        Telescope::recordQuery(IncomingEntry::make([
            'sql' => $event->sql,
            'time' => $event->time,
            'connection' => $event->connectionName,
        ]));
    }
}
```

**Strengths:**
- Event-driven, decoupled
- Watcher pattern is clean
- Easy to add custom watchers

**Weaknesses:**
- Laravel-specific
- Storage-focused, not metrics-focused

---

## Proposed Architecture for InstructorPHP

### Design Goals

1. **Event-driven** - Collectors subscribe to PSR-14 events
2. **Composable** - Multiple independent collectors
3. **Pluggable** - Easy to add custom collectors
4. **Zero-overhead when disabled** - No impact if not used
5. **Export-agnostic** - Support multiple backends
6. **Type-safe** - Leverage PHP 8.2+ type system

### Core Concepts

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         MetricsManager                                   │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │
│  │  Collector  │  │  Collector  │  │  Collector  │  │  Collector  │    │
│  │ (Inference) │  │ (HTTP)      │  │ (Custom)    │  │ (Memory)    │    │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘    │
│         │                │                │                │            │
│         ▼                ▼                ▼                ▼            │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                    MetricsRegistry                               │   │
│  │  (Stores: Counters, Gauges, Histograms, Timers)                 │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                │                                        │
│                                ▼                                        │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                    MetricsExporter[]                             │   │
│  │  (Prometheus, OpenTelemetry, StatsD, Log, InMemory, Custom)     │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
         ▲
         │ subscribes to events
         │
┌─────────────────────────────────────────────────────────────────────────┐
│                    PSR-14 EventDispatcher                               │
│  (InferenceStarted, InferenceCompleted, HttpRequestSent, etc.)         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Component Breakdown

#### 1. Metric Types (Value Objects)

```php
// Base metric interface
interface Metric {
    public function name(): string;
    public function tags(): Tags;
    public function timestamp(): DateTimeImmutable;
}

// Counter - monotonically increasing
final readonly class Counter implements Metric {
    public function __construct(
        public string $name,
        public float $value,
        public Tags $tags,
        public DateTimeImmutable $timestamp,
    ) {}
}

// Gauge - point-in-time value
final readonly class Gauge implements Metric {
    public function __construct(
        public string $name,
        public float $value,
        public Tags $tags,
        public DateTimeImmutable $timestamp,
    ) {}
}

// Histogram - distribution of values
final readonly class Histogram implements Metric {
    public function __construct(
        public string $name,
        public float $value,
        public Tags $tags,
        public DateTimeImmutable $timestamp,
        public ?array $buckets = null, // Optional bucket boundaries
    ) {}
}

// Timer - duration measurement (specialized histogram)
final readonly class Timer implements Metric {
    public function __construct(
        public string $name,
        public float $durationMs,
        public Tags $tags,
        public DateTimeImmutable $timestamp,
        public ?float $startTime = null,
    ) {}
}

// Tags - dimensional metadata
final readonly class Tags implements \IteratorAggregate, \Countable {
    /** @param array<string, string|int|float|bool> $values */
    public function __construct(
        private array $values = [],
    ) {}

    public static function of(array $values): self;
    public function with(string $key, string|int|float|bool $value): self;
    public function merge(Tags $other): self;
    public function toArray(): array;
}
```

#### 2. MetricsRegistry (Metric Storage)

```php
interface MetricsRegistry {
    public function counter(string $name, Tags $tags, float $increment = 1): void;
    public function gauge(string $name, Tags $tags, float $value): void;
    public function histogram(string $name, Tags $tags, float $value): void;
    public function timer(string $name, Tags $tags, float $durationMs): void;

    /** @return iterable<Metric> */
    public function all(): iterable;

    /** @return iterable<Metric> */
    public function byName(string $name): iterable;

    public function clear(): void;
}

// In-memory implementation
final class InMemoryMetricsRegistry implements MetricsRegistry {
    /** @var array<string, list<Metric>> */
    private array $metrics = [];

    public function counter(string $name, Tags $tags, float $increment = 1): void {
        $this->metrics[$name][] = new Counter($name, $increment, $tags, new DateTimeImmutable());
    }

    // ... other methods
}

// Aggregating implementation (aggregates by name+tags)
final class AggregatingMetricsRegistry implements MetricsRegistry {
    /** @var array<string, AggregatedMetric> */
    private array $aggregates = [];

    public function counter(string $name, Tags $tags, float $increment = 1): void {
        $key = $this->key($name, $tags);
        if (!isset($this->aggregates[$key])) {
            $this->aggregates[$key] = new AggregatedCounter($name, $tags);
        }
        $this->aggregates[$key]->add($increment);
    }
}
```

#### 3. Collector Interface (Event Subscribers)

```php
/**
 * Collectors subscribe to events and record metrics.
 * They are independent and composable.
 */
interface MetricsCollector {
    /**
     * Returns the event classes this collector subscribes to.
     * @return array<class-string, string> Event class => method name
     */
    public function subscribedEvents(): array;

    /**
     * Sets the registry to record metrics to.
     */
    public function setRegistry(MetricsRegistry $registry): void;
}

/**
 * Base class with common functionality.
 */
abstract class AbstractMetricsCollector implements MetricsCollector {
    protected MetricsRegistry $registry;

    public function setRegistry(MetricsRegistry $registry): void {
        $this->registry = $registry;
    }

    protected function counter(string $name, array $tags = [], float $increment = 1): void {
        $this->registry->counter($name, Tags::of($tags), $increment);
    }

    protected function gauge(string $name, array $tags = [], float $value): void {
        $this->registry->gauge($name, Tags::of($tags), $value);
    }

    protected function histogram(string $name, array $tags = [], float $value): void {
        $this->registry->histogram($name, Tags::of($tags), $value);
    }

    protected function timer(string $name, array $tags = [], float $durationMs): void {
        $this->registry->timer($name, Tags::of($tags), $durationMs);
    }
}
```

#### 4. MetricsManager (Orchestrator)

```php
/**
 * Central manager that wires collectors to events and registry.
 */
final class MetricsManager {
    /** @var MetricsCollector[] */
    private array $collectors = [];
    private MetricsRegistry $registry;
    /** @var MetricsExporter[] */
    private array $exporters = [];

    public function __construct(
        private EventDispatcherInterface $events,
        ?MetricsRegistry $registry = null,
    ) {
        $this->registry = $registry ?? new InMemoryMetricsRegistry();
    }

    public function register(MetricsCollector $collector): self {
        $collector->setRegistry($this->registry);
        $this->collectors[] = $collector;

        // Subscribe collector methods to events
        foreach ($collector->subscribedEvents() as $eventClass => $method) {
            $this->events->addListener($eventClass, [$collector, $method]);
        }

        return $this;
    }

    public function addExporter(MetricsExporter $exporter): self {
        $this->exporters[] = $exporter;
        return $this;
    }

    public function export(): void {
        foreach ($this->exporters as $exporter) {
            $exporter->export($this->registry->all());
        }
    }

    public function registry(): MetricsRegistry {
        return $this->registry;
    }
}
```

#### 5. Exporters (Output Adapters)

```php
interface MetricsExporter {
    /** @param iterable<Metric> $metrics */
    public function export(iterable $metrics): void;
}

// Log exporter
final class LogMetricsExporter implements MetricsExporter {
    public function __construct(
        private LoggerInterface $logger,
        private string $level = LogLevel::INFO,
    ) {}

    public function export(iterable $metrics): void {
        foreach ($metrics as $metric) {
            $this->logger->log($this->level, (string) $metric, $metric->toArray());
        }
    }
}

// Prometheus text format exporter
final class PrometheusExporter implements MetricsExporter {
    public function export(iterable $metrics): void {
        // Renders Prometheus text format
    }

    public function render(): string {
        // Returns text for /metrics endpoint
    }
}

// OpenTelemetry exporter
final class OTLPExporter implements MetricsExporter {
    public function __construct(
        private string $endpoint,
        private HttpClientInterface $http,
    ) {}

    public function export(iterable $metrics): void {
        // Send to OTLP endpoint
    }
}

// Callback exporter (for custom handling)
final class CallbackExporter implements MetricsExporter {
    public function __construct(
        private Closure $callback,
    ) {}

    public function export(iterable $metrics): void {
        ($this->callback)($metrics);
    }
}
```

---

## Inference Stats as a Collector

Now the inference stats become just **one collector** among potentially many:

```php
/**
 * Collects inference-related metrics from Polyglot events.
 */
final class InferenceMetricsCollector extends AbstractMetricsCollector {
    /** @var array<string, InferenceState> */
    private array $executions = [];

    public function subscribedEvents(): array {
        return [
            InferenceStarted::class => 'onInferenceStarted',
            InferenceCompleted::class => 'onInferenceCompleted',
            StreamFirstChunkReceived::class => 'onFirstChunk',
            InferenceAttemptStarted::class => 'onAttemptStarted',
            InferenceAttemptSucceeded::class => 'onAttemptSucceeded',
            InferenceAttemptFailed::class => 'onAttemptFailed',
            InferenceUsageReported::class => 'onUsageReported',
        ];
    }

    public function onInferenceStarted(InferenceStarted $event): void {
        $this->executions[$event->executionId] = new InferenceState(
            startedAt: $event->startedAt,
            model: $event->request->model(),
            isStreamed: $event->isStreamed,
        );

        $this->counter('inference.started', [
            'model' => $event->request->model(),
            'streamed' => $event->isStreamed ? 'true' : 'false',
        ]);
    }

    public function onFirstChunk(StreamFirstChunkReceived $event): void {
        if (isset($this->executions[$event->executionId])) {
            $this->executions[$event->executionId]->ttfcMs = $event->timeToFirstChunkMs;

            $this->histogram('inference.ttfc_ms', [
                'model' => $event->model,
            ], $event->timeToFirstChunkMs);
        }
    }

    public function onInferenceCompleted(InferenceCompleted $event): void {
        $state = $this->executions[$event->executionId] ?? null;
        $tags = [
            'model' => $state?->model ?? 'unknown',
            'streamed' => ($state?->isStreamed ?? false) ? 'true' : 'false',
            'success' => $event->isSuccess ? 'true' : 'false',
            'finish_reason' => $event->finishReason?->value ?? 'unknown',
        ];

        $this->counter('inference.completed', $tags);
        $this->timer('inference.duration_ms', $tags, $event->durationMs);

        if ($event->usage !== null) {
            $this->histogram('inference.tokens.input', $tags, $event->usage->inputTokens);
            $this->histogram('inference.tokens.output', $tags, $event->usage->outputTokens);
            $this->histogram('inference.tokens.total', $tags, $event->usage->total());

            // Calculate throughput
            $durationSec = $event->durationMs / 1000;
            if ($durationSec > 0) {
                $this->gauge('inference.throughput.tokens_per_sec', $tags,
                    $event->usage->outputTokens / $durationSec);
            }
        }

        // TTFC if available
        if ($state?->ttfcMs !== null) {
            $this->histogram('inference.ttfc_ms', $tags, $state->ttfcMs);
        }

        // Cleanup
        unset($this->executions[$event->executionId]);
    }

    public function onAttemptFailed(InferenceAttemptFailed $event): void {
        $this->counter('inference.attempts.failed', [
            'error_type' => $event->errorType ?? 'unknown',
            'http_status' => (string) ($event->httpStatusCode ?? 0),
            'will_retry' => $event->willRetry ? 'true' : 'false',
        ]);
    }

    // ... other event handlers
}

// Internal state tracking
final class InferenceState {
    public ?float $ttfcMs = null;
    public int $attemptCount = 0;

    public function __construct(
        public DateTimeImmutable $startedAt,
        public string $model,
        public bool $isStreamed,
    ) {}
}
```

---

## Usage Example

```php
use Cognesy\Metrics\MetricsManager;
use Cognesy\Metrics\Registry\InMemoryMetricsRegistry;
use Cognesy\Metrics\Collectors\InferenceMetricsCollector;
use Cognesy\Metrics\Exporters\LogMetricsExporter;
use Cognesy\Metrics\Exporters\PrometheusExporter;

// Setup
$events = new EventDispatcher();
$metrics = new MetricsManager($events);

// Register collectors
$metrics->register(new InferenceMetricsCollector());
$metrics->register(new HttpMetricsCollector());  // HTTP client metrics
$metrics->register(new MemoryMetricsCollector()); // Memory usage

// Add exporters
$metrics->addExporter(new LogMetricsExporter($logger));
$metrics->addExporter(new PrometheusExporter());

// Use Inference normally - metrics collected automatically
$inference = new Inference(events: $events);
$response = $inference->with($messages)->response();

// Export metrics (e.g., on request end)
$metrics->export();

// Or access registry directly
foreach ($metrics->registry()->byName('inference.duration_ms') as $timer) {
    echo "{$timer->name}: {$timer->durationMs}ms\n";
}
```

---

## Package Structure

### Decision: `packages/metrics`

Start focused. Can be integrated into future `Telemetry` facade if needed.

```
packages/metrics/
├── src/
│   ├── Contracts/
│   │   ├── CanCollectMetrics.php      # Collector interface
│   │   ├── CanExportMetrics.php       # Exporter interface
│   │   └── CanStoreMetrics.php        # Registry interface
│   ├── Data/
│   │   ├── Metric.php                 # Base metric (abstract or interface)
│   │   ├── Counter.php
│   │   ├── Gauge.php
│   │   ├── Histogram.php
│   │   ├── Timer.php
│   │   └── Tags.php
│   ├── Registry/
│   │   ├── InMemoryRegistry.php
│   │   └── AggregatingRegistry.php
│   ├── Collectors/
│   │   └── MetricsCollector.php       # Abstract base class
│   ├── Exporters/
│   │   ├── LogExporter.php
│   │   ├── PrometheusExporter.php
│   │   ├── CallbackExporter.php
│   │   └── NullExporter.php
│   └── Metrics.php                    # Facade
└── tests/
```

Domain-specific collectors live in their packages:
```
packages/polyglot/src/Inference/Metrics/
├── InferenceMetricsCollector.php
└── InferenceExecutionState.php
```

---

## Metrics Facade Design

### Design Decisions

1. **Return values**: Methods return the created `Metric` object, throw exceptions on errors
2. **Naming**: Facade is `Metrics`, interfaces follow `CanX` convention
3. **Future**: Can be wrapped by `Telemetry` facade later if tracing/logging added

### Metrics Facade

```php
namespace Cognesy\Metrics;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Facade for the metrics collection system.
 *
 * Wires collectors to events and registry, coordinates export.
 */
final class Metrics
{
    /** @var CanCollectMetrics[] */
    private array $collectors = [];

    /** @var CanExportMetrics[] */
    private array $exporters = [];

    private CanStoreMetrics $registry;

    public function __construct(
        private EventDispatcherInterface $events,
        ?CanStoreMetrics $registry = null,
    ) {
        $this->registry = $registry ?? new InMemoryRegistry();
    }

    /**
     * Register a collector that subscribes to events.
     */
    public function collect(CanCollectMetrics $collector): self {
        $collector->register($this->events, $this->registry);
        $this->collectors[] = $collector;
        return $this;
    }

    /**
     * Add an exporter for metrics output.
     */
    public function exportTo(CanExportMetrics $exporter): self {
        $this->exporters[] = $exporter;
        return $this;
    }

    /**
     * Export all collected metrics to registered exporters.
     */
    public function export(): void {
        $metrics = $this->registry->all();
        foreach ($this->exporters as $exporter) {
            $exporter->export($metrics);
        }
    }

    /**
     * Access the registry directly for queries.
     */
    public function registry(): CanStoreMetrics {
        return $this->registry;
    }

    /**
     * Clear all collected metrics.
     */
    public function clear(): void {
        $this->registry->clear();
    }
}
```

### Contracts

```php
namespace Cognesy\Metrics\Contracts;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Collector that subscribes to events and records metrics.
 */
interface CanCollectMetrics
{
    /**
     * Register event listeners and set up the collector.
     */
    public function register(
        EventDispatcherInterface $events,
        CanStoreMetrics $registry,
    ): void;
}

/**
 * Registry that stores metrics.
 */
interface CanStoreMetrics
{
    public function counter(string $name, Tags $tags, float $increment = 1): Counter;
    public function gauge(string $name, Tags $tags, float $value): Gauge;
    public function histogram(string $name, Tags $tags, float $value): Histogram;
    public function timer(string $name, Tags $tags, float $durationMs): Timer;

    /** @return iterable<Metric> */
    public function all(): iterable;

    /** @return iterable<Metric> */
    public function find(string $name, ?Tags $tags = null): iterable;

    public function clear(): void;
}

/**
 * Exporter that outputs metrics to a backend.
 */
interface CanExportMetrics
{
    /** @param iterable<Metric> $metrics */
    public function export(iterable $metrics): void;
}
```

### Metric Value Objects

```php
namespace Cognesy\Metrics\Data;

use DateTimeImmutable;

/**
 * Base interface for all metric types.
 */
interface Metric
{
    public function name(): string;
    public function tags(): Tags;
    public function timestamp(): DateTimeImmutable;
    public function value(): float;
    public function toArray(): array;
}

/**
 * Counter - monotonically increasing value.
 */
final readonly class Counter implements Metric
{
    public function __construct(
        private string $name,
        private float $value,
        private Tags $tags,
        private DateTimeImmutable $timestamp,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('Counter value must be non-negative');
        }
    }

    public function name(): string { return $this->name; }
    public function value(): float { return $this->value; }
    public function tags(): Tags { return $this->tags; }
    public function timestamp(): DateTimeImmutable { return $this->timestamp; }

    public function toArray(): array {
        return [
            'type' => 'counter',
            'name' => $this->name,
            'value' => $this->value,
            'tags' => $this->tags->toArray(),
            'timestamp' => $this->timestamp->format('c'),
        ];
    }

    public function __toString(): string {
        return sprintf('%s{%s} %g', $this->name, $this->tags, $this->value);
    }
}

/**
 * Gauge - point-in-time value that can go up or down.
 */
final readonly class Gauge implements Metric
{
    public function __construct(
        private string $name,
        private float $value,
        private Tags $tags,
        private DateTimeImmutable $timestamp,
    ) {}

    // ... similar to Counter
}

/**
 * Histogram - distribution of values.
 */
final readonly class Histogram implements Metric
{
    public function __construct(
        private string $name,
        private float $value,
        private Tags $tags,
        private DateTimeImmutable $timestamp,
    ) {}

    // ... similar to Counter
}

/**
 * Timer - duration measurement (specialized histogram).
 */
final readonly class Timer implements Metric
{
    public function __construct(
        private string $name,
        private float $durationMs,
        private Tags $tags,
        private DateTimeImmutable $timestamp,
    ) {
        if ($durationMs < 0) {
            throw new InvalidArgumentException('Timer duration must be non-negative');
        }
    }

    public function value(): float { return $this->durationMs; }
    public function durationMs(): float { return $this->durationMs; }
    public function durationSeconds(): float { return $this->durationMs / 1000; }

    // ... rest similar to Counter
}

/**
 * Dimensional metadata for metrics.
 */
final readonly class Tags implements \IteratorAggregate, \Countable, \Stringable
{
    /** @param array<string, string|int|float|bool> $values */
    public function __construct(
        private array $values = [],
    ) {}

    public static function empty(): self {
        return new self([]);
    }

    public static function of(array $values): self {
        return new self($values);
    }

    public function with(string $key, string|int|float|bool $value): self {
        return new self([...$this->values, $key => $value]);
    }

    public function merge(self $other): self {
        return new self([...$this->values, ...$other->values]);
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool {
        return isset($this->values[$key]);
    }

    public function toArray(): array {
        return $this->values;
    }

    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->values);
    }

    public function count(): int {
        return count($this->values);
    }

    public function __toString(): string {
        $parts = [];
        foreach ($this->values as $key => $value) {
            $parts[] = sprintf('%s="%s"', $key, $value);
        }
        return implode(',', $parts);
    }
}
```

### Abstract Collector Base Class

```php
namespace Cognesy\Metrics\Collectors;

use Cognesy\Metrics\Contracts\CanCollectMetrics;
use Cognesy\Metrics\Contracts\CanStoreMetrics;
use Cognesy\Metrics\Data\{Counter, Gauge, Histogram, Timer, Tags};
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for metrics collectors with convenience methods.
 */
abstract class MetricsCollector implements CanCollectMetrics
{
    protected CanStoreMetrics $registry;

    /**
     * Returns event class => method name mapping.
     * @return array<class-string, string>
     */
    abstract protected function listeners(): array;

    public function register(
        EventDispatcherInterface $events,
        CanStoreMetrics $registry,
    ): void {
        $this->registry = $registry;

        foreach ($this->listeners() as $eventClass => $method) {
            $events->addListener($eventClass, [$this, $method]);
        }
    }

    protected function counter(string $name, array $tags = [], float $increment = 1): Counter {
        return $this->registry->counter($name, Tags::of($tags), $increment);
    }

    protected function gauge(string $name, array $tags = [], float $value): Gauge {
        return $this->registry->gauge($name, Tags::of($tags), $value);
    }

    protected function histogram(string $name, array $tags = [], float $value): Histogram {
        return $this->registry->histogram($name, Tags::of($tags), $value);
    }

    protected function timer(string $name, array $tags = [], float $durationMs): Timer {
        return $this->registry->timer($name, Tags::of($tags), $durationMs);
    }
}
```

### Usage Example

```php
use Cognesy\Metrics\Metrics;
use Cognesy\Metrics\Exporters\LogExporter;
use Cognesy\Polyglot\Inference\Metrics\InferenceMetricsCollector;

// Setup
$events = new EventDispatcher();
$metrics = new Metrics($events);

// Register collectors and exporters
$metrics
    ->collect(new InferenceMetricsCollector())
    ->collect(new HttpMetricsCollector())
    ->exportTo(new LogExporter($logger))
    ->exportTo(new PrometheusExporter());

// Use inference normally - metrics collected automatically
$inference = new Inference(events: $events);
$response = $inference->with($messages)->response();

// Export when ready (e.g., end of request)
$metrics->export();

// Or query registry directly
foreach ($metrics->registry()->find('inference.duration_ms') as $timer) {
    echo "Duration: {$timer->durationMs()}ms\n";
}
```

### Future Telemetry Integration

When tracing/logging is added, `Metrics` can be wrapped:

```php
final class Telemetry
{
    public function __construct(
        private Metrics $metrics,
        private ?Tracing $tracing = null,
        private ?Logging $logging = null,
    ) {}

    public function metrics(): Metrics {
        return $this->metrics;
    }

    public function tracing(): Tracing {
        return $this->tracing ?? throw new RuntimeException('Tracing not configured');
    }

    // Or Metrics facade could BE Telemetry with metrics() returning $this
}
```

---

## Comparison with Current Implementation

| Aspect | Current | Proposed |
|--------|---------|----------|
| **Coupling** | Stats logic in PendingInference/InferenceStream | Separate collector class |
| **Composability** | None | Multiple independent collectors |
| **Extensibility** | Modify core classes | Add new collector |
| **Zero-overhead** | Always runs | Only when collector registered |
| **Export** | Events only | Multiple backends |
| **Metric types** | Custom stats classes | Standard Counter/Gauge/Histogram/Timer |
| **Tags/Labels** | Ad-hoc | First-class Tags object |

---

## Migration Path

### Phase 1: Create packages/metrics
- Implement core metric types
- Implement MetricsRegistry
- Implement MetricsManager
- Implement basic exporters

### Phase 2: Create InferenceMetricsCollector
- Implement collector in packages/polyglot
- Subscribe to existing events
- No changes to PendingInference/InferenceStream core logic

### Phase 3: Remove Embedded Stats
- Remove stats calculation from PendingInference
- Remove stats calculation from InferenceStream
- Remove InferenceAttemptStats, InferenceExecutionStats (replaced by metrics)
- Keep or remove stats events (TBD)

### Phase 4: Additional Collectors
- HttpMetricsCollector for http-client package
- EmbeddingsMetricsCollector for embeddings
- Custom collectors for Instructor

---

## Decisions Made

| Question | Decision | Rationale |
|----------|----------|-----------|
| **Return values** | Return `Metric` objects, throw on errors | Enables inspection, testing, chaining |
| **Facade naming** | `Metrics` (not Manager) | DDD - meaningful name, not lazy "Manager" |
| **Package naming** | `packages/metrics` | Focused scope, can integrate into `Telemetry` later |
| **Interface naming** | `CanX` convention | Matches codebase style |

## Open Questions

1. **Should we keep the stats events after migration?**
   - Option A: Remove them (metrics replace them)
   - Option B: Keep them (different use case - events for workflow, metrics for observability)

2. **Aggregation strategy?**
   - Option A: Store all raw metrics (memory concern for long-running)
   - Option B: Aggregate by name+tags (lose granularity)
   - Option C: Time-windowed buckets (complex)
   - Option D: Configurable per registry implementation

3. **Export trigger?**
   - Option A: Manual `export()` call
   - Option B: Register shutdown handler
   - Option C: Periodic export via timer
   - Likely: Manual for now, add helpers later

---

## Next Steps

1. Review this design
2. Decide on package name/location
3. Implement core metric types and registry
4. Implement MetricsManager
5. Implement InferenceMetricsCollector
6. Add tests
7. Remove embedded stats from core classes
