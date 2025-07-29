<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Middleware\TimingMiddleware;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Tags\TimingTag;
use Cognesy\Utils\Result\Result;

describe('TimingMiddleware Unit Tests', function () {
    it('measures execution time for successful operations', function () {
        $start = microtime(true);
        
        $result = Pipeline::for('test')
            ->withMiddleware(TimingMiddleware::for('test_operation'))
            ->through(function($x) {
                usleep(1000); // 1ms delay
                return strtoupper($x);
            })
            ->process();

        $computation = $result->computation();
        $timing = $computation->last(TimingTag::class);

        expect($timing)->toBeInstanceOf(TimingTag::class);
        expect($timing->operationName)->toBe('test_operation');
        expect($timing->isSuccess())->toBeTrue();
        expect($timing->duration)->toBeGreaterThan(0.0009); // At least 0.9ms
        expect($timing->duration)->toBeLessThan(0.1); // Less than 100ms
        expect($timing->startTime)->toBeGreaterThanOrEqual($start);
        expect($timing->endTime)->toBeGreaterThan($timing->startTime);
    });

    it('measures execution time for failed operations', function () {
        $result = Pipeline::for('test')
            ->withMiddleware(TimingMiddleware::for('failing_operation'))
            ->through(function($x) {
                usleep(500); // 0.5ms delay before failure
                throw new \RuntimeException('Test failure');
            })
            ->process();

        expect($result->success())->toBeFalse();
        
        $computation = $result->computation();
        $timings = $computation->all(TimingTag::class);
        
        // Debug: let's see what we have
        expect(count($timings))->toBeGreaterThan(0);
        
        $timing = $computation->last(TimingTag::class);
        expect($timing)->toBeInstanceOf(TimingTag::class);
        expect($timing->operationName)->toBe('failing_operation');
        
        // The timing should show this was a failure  
        expect($timing->isFailure())->toBeTrue();
        expect($timing->error)->toBe('Test failure');
        expect($timing->duration)->toBeGreaterThan(0.0004); // At least 0.4ms
    });

    it('works without operation name', function () {
        $result = Pipeline::for(42)
            ->withMiddleware(TimingMiddleware::create())
            ->through(fn($x) => $x * 2)
            ->process();

        $timing = $result->computation()->last(TimingTag::class);

        expect($timing->operationName)->toBeNull();
        expect($timing->isSuccess())->toBeTrue();
        expect($timing->duration)->toBeGreaterThan(0);
    });

    it('supports custom precision', function () {
        $result = Pipeline::for('test')
            ->withMiddleware(TimingMiddleware::create(precision: 3))
            ->through(function($x) {
                usleep(1234); // 1.234ms
                return $x;
            })
            ->process();

        $timing = $result->computation()->last(TimingTag::class);
        
        // Duration should be rounded to 3 decimal places
        $durationStr = (string)$timing->duration;
        $decimalPart = explode('.', $durationStr)[1] ?? '';
        expect(strlen($decimalPart))->toBeLessThanOrEqual(3);
    });

    it('accumulates multiple timing tags', function () {
        $result = Pipeline::for(1)
            ->withMiddleware(TimingMiddleware::for('step1'))
            ->through(function($x) {
                usleep(500);
                return $x + 1;
            })
            ->withMiddleware(TimingMiddleware::for('step2'))
            ->through(function($x) {
                usleep(300);
                return $x * 2;
            })
            ->withMiddleware(TimingMiddleware::for('step3'))
            ->through(function($x) {
                usleep(200);
                return $x + 10;
            })
            ->process();

        $timings = $result->computation()->all(TimingTag::class);
        
        // Each middleware creates timing tags for each processor it wraps
        expect(count($timings))->toBeGreaterThanOrEqual(3);
        
        // Find timings by operation name
        $step1Timings = array_filter($timings, fn($t) => $t->operationName === 'step1');
        $step2Timings = array_filter($timings, fn($t) => $t->operationName === 'step2');
        $step3Timings = array_filter($timings, fn($t) => $t->operationName === 'step3');
        
        expect(count($step1Timings))->toBeGreaterThanOrEqual(1);
        expect(count($step2Timings))->toBeGreaterThanOrEqual(1);
        expect(count($step3Timings))->toBeGreaterThanOrEqual(1);
        
        foreach ($timings as $timing) {
            expect($timing->isSuccess())->toBeTrue();
            expect($timing->duration)->toBeGreaterThan(0);
        }
    });

    it('works with computation-aware processors', function () {
        $result = Pipeline::for('data')
            ->withMiddleware(TimingMiddleware::for('computation_processor'))
            ->through(function(Computation $computation) {
                $value = $computation->result()->unwrap();
                return $computation->withResult(Result::success(strtoupper($value)));
            })
            ->process();

        $timing = $result->computation()->last(TimingTag::class);
        
        expect($timing->operationName)->toBe('computation_processor');
        expect($timing->isSuccess())->toBeTrue();
        expect($result->value())->toBe('DATA');
    });
});

describe('TimingTag Unit Tests', function () {
    beforeEach(function () {
        $this->startTime = 1700000000.123456;
        $this->endTime = 1700000000.125678;
        $this->duration = $this->endTime - $this->startTime;
        
        $this->successTag = new TimingTag(
            startTime: $this->startTime,
            endTime: $this->endTime,
            duration: $this->duration,
            operationName: 'test_op',
            success: true
        );

        $this->failureTag = new TimingTag(
            startTime: $this->startTime,
            endTime: $this->endTime,
            duration: $this->duration,
            operationName: 'failed_op',
            success: false,
            error: 'Test error'
        );
    });

    it('calculates duration in different units', function () {
        expect($this->successTag->durationMs())->toBeCloseTo(2.222, 2);
        expect($this->successTag->durationMicros())->toBeCloseTo(2222, 0);
    });

    it('formats duration appropriately', function () {
        // Test microsecond formatting (< 1ms)
        $microTag = new TimingTag(0, 0, 0.0005, 'micro_op'); // 0.5ms = 500μs
        expect($microTag->durationFormatted())->toBe('500μs');

        // Test millisecond formatting (< 1s)
        expect($this->successTag->durationFormatted())->toBe('2.22ms');

        // Test second formatting (>= 1s)
        $longTag = new TimingTag(0, 0, 1.5, 'long_op');
        expect($longTag->durationFormatted())->toBe('1.500s');
    });

    it('creates DateTime objects correctly', function () {
        $startDateTime = $this->successTag->startDateTime();
        $endDateTime = $this->successTag->endDateTime();

        expect($startDateTime)->toBeInstanceOf(\DateTimeImmutable::class);
        expect($endDateTime)->toBeInstanceOf(\DateTimeImmutable::class);
        
        // The timestamps should be different (endTime > startTime)
        expect($endDateTime->format('U.u'))->toBeGreaterThan($startDateTime->format('U.u'));
    });

    it('provides success/failure status', function () {
        expect($this->successTag->isSuccess())->toBeTrue();
        expect($this->successTag->isFailure())->toBeFalse();

        expect($this->failureTag->isSuccess())->toBeFalse();
        expect($this->failureTag->isFailure())->toBeTrue();
    });

    it('generates summary strings', function () {
        $successSummary = $this->successTag->summary();
        expect($successSummary)->toContain('test_op');
        expect($successSummary)->toContain('SUCCESS');
        expect($successSummary)->toContain('2.22ms');

        $failureSummary = $this->failureTag->summary();
        expect($failureSummary)->toContain('failed_op');
        expect($failureSummary)->toContain('FAILED');
        expect($failureSummary)->toContain('Test error');
    });

    it('converts to array correctly', function () {
        $array = $this->successTag->toArray();

        expect($array)->toHaveKey('operation_name');
        expect($array)->toHaveKey('start_time');
        expect($array)->toHaveKey('end_time');
        expect($array)->toHaveKey('duration_seconds');
        expect($array)->toHaveKey('duration_ms');
        expect($array)->toHaveKey('success');
        expect($array)->toHaveKey('formatted_duration');

        expect($array['operation_name'])->toBe('test_op');
        expect($array['success'])->toBeTrue();
        expect($array['duration_ms'])->toBeCloseTo(2.222, 2);
    });

    it('creates tags with static factory methods', function () {
        $successTag = TimingTag::success(1.0, 2.5, 'success_op');
        expect($successTag->operationName)->toBe('success_op');
        expect($successTag->isSuccess())->toBeTrue();
        expect($successTag->duration)->toBe(1.5);

        $failureTag = TimingTag::failure(1.0, 2.0, 'Error occurred', 'failure_op');
        expect($failureTag->operationName)->toBe('failure_op');
        expect($failureTag->isFailure())->toBeTrue();
        expect($failureTag->error)->toBe('Error occurred');
        expect($failureTag->duration)->toBe(1.0);
    });
});