<?php
require 'examples/boot.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Metrics\Collectors\MetricsCollector;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Metrics\Exporters\CallbackExporter;
use Cognesy\Metrics\Metrics;

/**
 * Metrics Collection Example
 *
 * Demonstrates the event-driven metrics collection system:
 * - Setting up the Metrics facade
 * - Creating custom collectors that listen to events
 * - Recording counters, gauges, histograms, and timers
 * - Exporting metrics to custom backends
 */

print("================================================================================\n");
print("              Metrics Collection - Event-Driven Metrics Demo                    \n");
print("================================================================================\n\n");

// --- Define domain events ---

final class RequestReceived {
    public function __construct(
        public string $method,
        public string $path,
        public float $startTime,
    ) {}
}

final class RequestCompleted {
    public function __construct(
        public string $method,
        public string $path,
        public int $statusCode,
        public float $startTime,
        public float $endTime,
        public int $responseBytes,
    ) {}
}

final class CacheEvent {
    public function __construct(
        public bool $hit,
        public string $key,
    ) {}
}

// --- Define a custom collector ---

final class HttpMetricsCollector extends MetricsCollector {
    #[\Override]
    protected function listeners(): array {
        return [
            RequestReceived::class => 'onRequestReceived',
            RequestCompleted::class => 'onRequestCompleted',
            CacheEvent::class => 'onCacheEvent',
        ];
    }

    public function onRequestReceived(RequestReceived $event): void {
        $this->counter('http.requests.started', [
            'method' => $event->method,
        ]);
    }

    public function onRequestCompleted(RequestCompleted $event): void {
        $durationMs = ($event->endTime - $event->startTime) * 1000;
        $statusClass = (string) intdiv($event->statusCode, 100) . 'xx';

        $this->counter('http.requests.completed', [
            'method' => $event->method,
            'status_class' => $statusClass,
        ]);

        $this->timer('http.request.duration', $durationMs, [
            'method' => $event->method,
            'path' => $event->path,
        ]);

        $this->histogram('http.response.size', (float) $event->responseBytes, [
            'method' => $event->method,
        ]);
    }

    public function onCacheEvent(CacheEvent $event): void {
        $this->counter('cache.operations', [
            'result' => $event->hit ? 'hit' : 'miss',
        ]);
    }
}

// --- Set up the metrics system ---

print("Setting up metrics system...\n\n");

$events = new EventDispatcher();
$metrics = new Metrics($events);

// Register collectors
$metrics->collect(new HttpMetricsCollector());

// Register exporters
$metrics->exportTo(new CallbackExporter(function (iterable $metrics) {
    print("Exporting " . iterator_count($metrics) . " metrics...\n");
    foreach ($metrics as $metric) {
        /** @var Metric $metric */
        $tags = $metric->tags()->toArray();
        $tagsStr = empty($tags)
            ? ''
            : ' {' . implode(', ', array_map(
                fn($k, $v) => "{$k}=\"{$v}\"",
                array_keys($tags),
                array_values($tags)
            )) . '}';

        printf(
            "  [%s] %s%s = %.2f\n",
            $metric->type(),
            $metric->name(),
            $tagsStr,
            $metric->value()
        );
    }
}));

// --- Simulate application events ---

print("Simulating application events...\n\n");

// Simulate HTTP requests
$requests = [
    ['GET', '/api/users', 200, 45.2, 1024],
    ['GET', '/api/users/123', 200, 32.1, 512],
    ['POST', '/api/users', 201, 78.5, 256],
    ['GET', '/api/products', 200, 55.0, 2048],
    ['GET', '/api/users/999', 404, 12.3, 64],
    ['GET', '/api/users', 200, 42.8, 1024],
];

foreach ($requests as [$method, $path, $status, $durationMs, $bytes]) {
    $startTime = microtime(true);
    $endTime = $startTime + ($durationMs / 1000);

    print("  Dispatching: {$method} {$path} -> {$status}\n");

    $events->dispatch(new RequestReceived($method, $path, $startTime));
    $events->dispatch(new RequestCompleted($method, $path, $status, $startTime, $endTime, $bytes));
}

// Simulate cache events
$cacheOps = [
    ['user:123', true],
    ['user:456', false],
    ['product:789', true],
    ['user:123', true],
    ['config:main', false],
];

print("\n  Simulating cache operations...\n");
foreach ($cacheOps as [$key, $hit]) {
    $result = $hit ? 'HIT' : 'MISS';
    print("    Cache {$result}: {$key}\n");
    $events->dispatch(new CacheEvent($hit, $key));
}

print("\n--------------------------------------------------------------------------------\n");
print("Exporting collected metrics:\n");
print("--------------------------------------------------------------------------------\n\n");

// Export all metrics
$metrics->export();

// --- Show summary stats ---

print("\n--------------------------------------------------------------------------------\n");
print("Summary:\n");
print("--------------------------------------------------------------------------------\n\n");

$registry = $metrics->registry();

printf("  Total metrics recorded: %d\n", $registry->count());

// Count by type
$counters = iterator_count($registry->find('http.requests.started'));
$timers = iterator_count($registry->find('http.request.duration'));
$histograms = iterator_count($registry->find('http.response.size'));
$cacheCounters = iterator_count($registry->find('cache.operations'));

print("  Breakdown:\n");
print("    - Request started counters: {$counters}\n");
print("    - Request completed counters: " . iterator_count($registry->find('http.requests.completed')) . "\n");
print("    - Duration timers: {$timers}\n");
print("    - Response size histograms: {$histograms}\n");
print("    - Cache operation counters: {$cacheCounters}\n");

print("\n================================================================================\n");
print("Metrics Collection Demo Complete\n");
print("================================================================================\n");
