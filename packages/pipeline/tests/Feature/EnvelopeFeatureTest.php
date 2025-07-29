<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\TagInterface;
use Cognesy\Utils\Result\Result;

// Test tags for feature testing
class FeatureTestTag implements TagInterface
{
    public function __construct(
        public readonly string $name,
        public readonly mixed $data = null
    ) {}
}

class MetricsTag implements TagInterface
{
    public function __construct(
        public readonly string $metric,
        public readonly float $value
    ) {}
}

class TraceTag implements TagInterface
{
    public function __construct(public readonly string $traceId) {}
}

describe('Computation Feature Tests', function () {
    it('manages complex tag lifecycle', function () {
        $computation = Computation::wrap('initial data', [
            new FeatureTestTag('created', time()),
            new TraceTag('trace-123')
        ]);

        // Add processing tags
        $computation = $computation
            ->with(new MetricsTag('duration', 0.5))
            ->with(new FeatureTestTag('processed', 'step1'));

        // Transform the message while preserving tags
        $computation = $computation->withResult(Result::success('processed data'));

        // Add more tags
        $computation = $computation
            ->with(new MetricsTag('memory', 1024.0))
            ->with(new FeatureTestTag('completed', 'final'));

        // Verify final state
        expect($computation->result()->unwrap())->toBe('processed data');
        expect($computation->count())->toBe(6); // All tags preserved
        expect($computation->count(FeatureTestTag::class))->toBe(3);
        expect($computation->count(MetricsTag::class))->toBe(2);
        expect($computation->count(TraceTag::class))->toBe(1);

        // Verify tag ordering
        $testTags = $computation->all(FeatureTestTag::class);
        expect($testTags[0]->name)->toBe('created');
        expect($testTags[1]->name)->toBe('processed');
        expect($testTags[2]->name)->toBe('completed');
    });

    it('handles tag filtering and removal', function () {
        $computation = Computation::wrap('data', [
            new FeatureTestTag('temp1'),
            new MetricsTag('cpu', 85.5),
            new FeatureTestTag('temp2'),
            new TraceTag('trace-456'),
            new MetricsTag('memory', 512.0)
        ]);

        // Remove temporary tags
        $cleaned = $computation->without(FeatureTestTag::class);

        expect($cleaned->count())->toBe(3);
        expect($cleaned->has(FeatureTestTag::class))->toBeFalse();
        expect($cleaned->has(MetricsTag::class))->toBeTrue();
        expect($cleaned->has(TraceTag::class))->toBeTrue();

        // Original computation unchanged (immutability)
        expect($computation->count())->toBe(5);
        expect($computation->has(FeatureTestTag::class))->toBeTrue();
    });

    it('supports tag querying patterns', function () {
        $computation = Computation::wrap('data')
            ->with(new MetricsTag('latency', 150.0))
            ->with(new MetricsTag('throughput', 1000.0))
            ->with(new TraceTag('parent-trace'))
            ->with(new MetricsTag('errors', 0.0));

        // Get all metrics
        $metrics = $computation->all(MetricsTag::class);
        expect($metrics)->toHaveCount(3);

        // Get latest metric
        $lastMetric = $computation->last(MetricsTag::class);
        expect($lastMetric->metric)->toBe('errors');
        expect($lastMetric->value)->toBe(0.0);

        // Get first metric
        $firstMetric = $computation->first(MetricsTag::class);
        expect($firstMetric->metric)->toBe('latency');
        expect($firstMetric->value)->toBe(150.0);

        // Check specific tag existence
        expect($computation->has(TraceTag::class))->toBeTrue();
        expect($computation->has(FeatureTestTag::class))->toBeFalse();
    });

    it('preserves tags through message transformations', function () {
        $originalData = ['user' => 'john', 'action' => 'login'];
        
        $computation = Computation::wrap($originalData, [
            new TraceTag('req-123'),
            new FeatureTestTag('request', 'received')
        ]);

        // Transform to success result
        $successComputation = $computation->withResult(
            Result::success(['status' => 'authenticated', 'user' => 'john'])
        );

        // Transform to failure result
        $failureComputation = $successComputation->withResult(
            Result::failure(new Exception('Authentication failed'))
        );

        // All transformations preserve tags
        expect($computation->count())->toBe(2);
        expect($successComputation->count())->toBe(2);
        expect($failureComputation->count())->toBe(2);

        // But messages are different
        expect($computation->result()->unwrap())->toBe($originalData);
        expect($successComputation->result()->unwrap()['status'])->toBe('authenticated');
        expect($failureComputation->result()->isFailure())->toBeTrue();

        // Tags remain accessible
        expect($failureComputation->first(TraceTag::class)->traceId)->toBe('req-123');
        expect($failureComputation->first(FeatureTestTag::class)->name)->toBe('request');
    });

    it('handles computation chaining with tags', function () {
        $computation = Computation::wrap('start');

        // Simulate processing pipeline
        $steps = ['validate', 'transform', 'persist', 'notify'];
        
        foreach ($steps as $step) {
            $computation = $computation
                ->with(new FeatureTestTag($step, microtime(true)))
                ->withResult(Result::success($computation->result()->unwrap() . " -> $step"));
        }

        expect($computation->result()->unwrap())
            ->toBe('start -> validate -> transform -> persist -> notify');
        
        expect($computation->count(FeatureTestTag::class))->toBe(4);
        
        // Verify processing order through tags
        $processTags = $computation->all(FeatureTestTag::class);
        expect($processTags[0]->name)->toBe('validate');
        expect($processTags[3]->name)->toBe('notify');
    });

    it('supports tag aggregation patterns', function () {
        $computation = Computation::wrap('data')
            ->with(new MetricsTag('requests', 100))
            ->with(new MetricsTag('errors', 5))
            ->with(new MetricsTag('duration', 250.5))
            ->with(new FeatureTestTag('region', 'us-east-1'))
            ->with(new FeatureTestTag('service', 'api'));

        // Aggregate metrics
        $metrics = $computation->all(MetricsTag::class);
        $totalRequests = array_reduce($metrics, 
            fn($sum, $tag) => $tag->metric === 'requests' ? $sum + $tag->value : $sum, 
            0
        );
        
        expect($totalRequests)->toEqual(100);

        // Extract metadata
        $metadata = [];
        foreach ($computation->all(FeatureTestTag::class) as $tag) {
            $metadata[$tag->name] = $tag->data;
        }
        
        expect($metadata)->toBe(['region' => 'us-east-1', 'service' => 'api']);
    });
});