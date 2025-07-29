<?php declare(strict_types=1);

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\StampInterface;
use Cognesy\Utils\Result\Result;

// Test stamps for feature testing
class FeatureTestStamp implements StampInterface
{
    public function __construct(
        public readonly string $name,
        public readonly mixed $data = null
    ) {}
}

class MetricsStamp implements StampInterface
{
    public function __construct(
        public readonly string $metric,
        public readonly float $value
    ) {}
}

class TraceStamp implements StampInterface
{
    public function __construct(public readonly string $traceId) {}
}

describe('Envelope Feature Tests', function () {
    it('manages complex stamp lifecycle', function () {
        $envelope = Envelope::wrap('initial data', [
            new FeatureTestStamp('created', time()),
            new TraceStamp('trace-123')
        ]);

        // Add processing stamps
        $envelope = $envelope
            ->with(new MetricsStamp('duration', 0.5))
            ->with(new FeatureTestStamp('processed', 'step1'));

        // Transform the message while preserving stamps
        $envelope = $envelope->withResult(Result::success('processed data'));

        // Add more stamps
        $envelope = $envelope
            ->with(new MetricsStamp('memory', 1024.0))
            ->with(new FeatureTestStamp('completed', 'final'));

        // Verify final state
        expect($envelope->result()->unwrap())->toBe('processed data');
        expect($envelope->count())->toBe(6); // All stamps preserved
        expect($envelope->count(FeatureTestStamp::class))->toBe(3);
        expect($envelope->count(MetricsStamp::class))->toBe(2);
        expect($envelope->count(TraceStamp::class))->toBe(1);

        // Verify stamp ordering
        $testStamps = $envelope->all(FeatureTestStamp::class);
        expect($testStamps[0]->name)->toBe('created');
        expect($testStamps[1]->name)->toBe('processed');
        expect($testStamps[2]->name)->toBe('completed');
    });

    it('handles stamp filtering and removal', function () {
        $envelope = Envelope::wrap('data', [
            new FeatureTestStamp('temp1'),
            new MetricsStamp('cpu', 85.5),
            new FeatureTestStamp('temp2'),
            new TraceStamp('trace-456'),
            new MetricsStamp('memory', 512.0)
        ]);

        // Remove temporary stamps
        $cleaned = $envelope->without(FeatureTestStamp::class);

        expect($cleaned->count())->toBe(3);
        expect($cleaned->has(FeatureTestStamp::class))->toBeFalse();
        expect($cleaned->has(MetricsStamp::class))->toBeTrue();
        expect($cleaned->has(TraceStamp::class))->toBeTrue();

        // Original envelope unchanged (immutability)
        expect($envelope->count())->toBe(5);
        expect($envelope->has(FeatureTestStamp::class))->toBeTrue();
    });

    it('supports stamp querying patterns', function () {
        $envelope = Envelope::wrap('data')
            ->with(new MetricsStamp('latency', 150.0))
            ->with(new MetricsStamp('throughput', 1000.0))
            ->with(new TraceStamp('parent-trace'))
            ->with(new MetricsStamp('errors', 0.0));

        // Get all metrics
        $metrics = $envelope->all(MetricsStamp::class);
        expect($metrics)->toHaveCount(3);

        // Get latest metric
        $lastMetric = $envelope->last(MetricsStamp::class);
        expect($lastMetric->metric)->toBe('errors');
        expect($lastMetric->value)->toBe(0.0);

        // Get first metric
        $firstMetric = $envelope->first(MetricsStamp::class);
        expect($firstMetric->metric)->toBe('latency');
        expect($firstMetric->value)->toBe(150.0);

        // Check specific stamp existence
        expect($envelope->has(TraceStamp::class))->toBeTrue();
        expect($envelope->has(FeatureTestStamp::class))->toBeFalse();
    });

    it('preserves stamps through message transformations', function () {
        $originalData = ['user' => 'john', 'action' => 'login'];
        
        $envelope = Envelope::wrap($originalData, [
            new TraceStamp('req-123'),
            new FeatureTestStamp('request', 'received')
        ]);

        // Transform to success result
        $successEnvelope = $envelope->withResult(
            Result::success(['status' => 'authenticated', 'user' => 'john'])
        );

        // Transform to failure result
        $failureEnvelope = $successEnvelope->withResult(
            Result::failure(new Exception('Authentication failed'))
        );

        // All transformations preserve stamps
        expect($envelope->count())->toBe(2);
        expect($successEnvelope->count())->toBe(2);
        expect($failureEnvelope->count())->toBe(2);

        // But messages are different
        expect($envelope->result()->unwrap())->toBe($originalData);
        expect($successEnvelope->result()->unwrap()['status'])->toBe('authenticated');
        expect($failureEnvelope->result()->isFailure())->toBeTrue();

        // Stamps remain accessible
        expect($failureEnvelope->first(TraceStamp::class)->traceId)->toBe('req-123');
        expect($failureEnvelope->first(FeatureTestStamp::class)->name)->toBe('request');
    });

    it('handles envelope chaining with stamps', function () {
        $envelope = Envelope::wrap('start');

        // Simulate processing pipeline
        $steps = ['validate', 'transform', 'persist', 'notify'];
        
        foreach ($steps as $step) {
            $envelope = $envelope
                ->with(new FeatureTestStamp($step, microtime(true)))
                ->withResult(Result::success($envelope->result()->unwrap() . " -> $step"));
        }

        expect($envelope->result()->unwrap())
            ->toBe('start -> validate -> transform -> persist -> notify');
        
        expect($envelope->count(FeatureTestStamp::class))->toBe(4);
        
        // Verify processing order through stamps
        $processStamps = $envelope->all(FeatureTestStamp::class);
        expect($processStamps[0]->name)->toBe('validate');
        expect($processStamps[3]->name)->toBe('notify');
    });

    it('supports stamp aggregation patterns', function () {
        $envelope = Envelope::wrap('data')
            ->with(new MetricsStamp('requests', 100))
            ->with(new MetricsStamp('errors', 5))
            ->with(new MetricsStamp('duration', 250.5))
            ->with(new FeatureTestStamp('region', 'us-east-1'))
            ->with(new FeatureTestStamp('service', 'api'));

        // Aggregate metrics
        $metrics = $envelope->all(MetricsStamp::class);
        $totalRequests = array_reduce($metrics, 
            fn($sum, $stamp) => $stamp->metric === 'requests' ? $sum + $stamp->value : $sum, 
            0
        );
        
        expect($totalRequests)->toEqual(100);

        // Extract metadata
        $metadata = [];
        foreach ($envelope->all(FeatureTestStamp::class) as $stamp) {
            $metadata[$stamp->name] = $stamp->data;
        }
        
        expect($metadata)->toBe(['region' => 'us-east-1', 'service' => 'api']);
    });
});