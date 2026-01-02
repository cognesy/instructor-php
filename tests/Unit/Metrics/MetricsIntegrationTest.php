<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Metrics\Collectors\MetricsCollector;
use Cognesy\Metrics\Data\Tags;
use Cognesy\Metrics\Exporters\CallbackExporter;
use Cognesy\Metrics\Metrics;

// Test event class
final class TestRequestEvent {
    public function __construct(
        public string $method,
        public int $statusCode,
    ) {}
}

// Test collector
final class TestRequestCollector extends MetricsCollector {
    #[\Override]
    protected function listeners(): array {
        return [
            TestRequestEvent::class => 'onRequest',
        ];
    }

    public function onRequest(TestRequestEvent $event): void {
        $this->counter('test.requests', [
            'method' => $event->method,
            'status' => (string) $event->statusCode,
        ]);
    }
}

test('collector registers listeners and records metrics on event dispatch', function () {
    $events = new EventDispatcher();
    $metrics = new Metrics($events);

    $metrics->collect(new TestRequestCollector());

    // Dispatch events
    $events->dispatch(new TestRequestEvent('GET', 200));
    $events->dispatch(new TestRequestEvent('POST', 201));
    $events->dispatch(new TestRequestEvent('GET', 404));

    expect($metrics->registry()->count())->toBe(3);

    $getRequests = iterator_to_array(
        $metrics->registry()->find('test.requests', Tags::of(['method' => 'GET', 'status' => '200']))
    );
    expect($getRequests)->toHaveCount(1);
});

test('export pushes metrics to all registered exporters', function () {
    $events = new EventDispatcher();
    $metrics = new Metrics($events);

    $exported1 = [];
    $exported2 = [];

    $metrics
        ->exportTo(new CallbackExporter(function (iterable $m) use (&$exported1) {
            $exported1 = iterator_to_array($m);
        }))
        ->exportTo(new CallbackExporter(function (iterable $m) use (&$exported2) {
            $exported2 = iterator_to_array($m);
        }));

    // Record directly via registry
    $metrics->registry()->counter('direct.counter', Tags::of([]), 1);
    $metrics->registry()->gauge('direct.gauge', Tags::of([]), 42.0);

    $metrics->export();

    expect($exported1)->toHaveCount(2);
    expect($exported2)->toHaveCount(2);
});

test('clear removes all metrics from registry', function () {
    $events = new EventDispatcher();
    $metrics = new Metrics($events);

    $metrics->registry()->counter('test', Tags::of([]), 1);
    $metrics->registry()->counter('test', Tags::of([]), 2);

    expect($metrics->registry()->count())->toBe(2);

    $metrics->clear();

    expect($metrics->registry()->count())->toBe(0);
});

test('collector throws exception for non-existent method', function () {
    $collector = new class extends MetricsCollector {
        #[\Override]
        protected function listeners(): array {
            return [
                TestRequestEvent::class => 'nonExistentMethod',
            ];
        }
    };

    $events = new EventDispatcher();
    $metrics = new Metrics($events);

    expect(fn() => $metrics->collect($collector))
        ->toThrow(RuntimeException::class, 'does not exist');
});

test('fluent interface returns self for chaining', function () {
    $events = new EventDispatcher();
    $metrics = new Metrics($events);

    $result = $metrics
        ->collect(new TestRequestCollector())
        ->exportTo(new CallbackExporter(fn() => null));

    expect($result)->toBe($metrics);
});
