<?php declare(strict_types=1);

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\Middleware\TimingMiddleware;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Stamps\TimingStamp;
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

        $envelope = $result->envelope();
        $timing = $envelope->last(TimingStamp::class);

        expect($timing)->toBeInstanceOf(TimingStamp::class);
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
        
        $envelope = $result->envelope();
        $timings = $envelope->all(TimingStamp::class);
        
        // Debug: let's see what we have
        expect(count($timings))->toBeGreaterThan(0);
        
        $timing = $envelope->last(TimingStamp::class);
        expect($timing)->toBeInstanceOf(TimingStamp::class);
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

        $timing = $result->envelope()->last(TimingStamp::class);

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

        $timing = $result->envelope()->last(TimingStamp::class);
        
        // Duration should be rounded to 3 decimal places
        $durationStr = (string)$timing->duration;
        $decimalPart = explode('.', $durationStr)[1] ?? '';
        expect(strlen($decimalPart))->toBeLessThanOrEqual(3);
    });

    it('accumulates multiple timing stamps', function () {
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

        $timings = $result->envelope()->all(TimingStamp::class);
        
        // Each middleware creates timing stamps for each processor it wraps
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

    it('works with envelope-aware processors', function () {
        $result = Pipeline::for('data')
            ->withMiddleware(TimingMiddleware::for('envelope_processor'))
            ->through(function(Envelope $env) {
                $value = $env->result()->unwrap();
                return $env->withResult(Result::success(strtoupper($value)));
            })
            ->process();

        $timing = $result->envelope()->last(TimingStamp::class);
        
        expect($timing->operationName)->toBe('envelope_processor');
        expect($timing->isSuccess())->toBeTrue();
        expect($result->payload())->toBe('DATA');
    });
});

describe('TimingStamp Unit Tests', function () {
    beforeEach(function () {
        $this->startTime = 1700000000.123456;
        $this->endTime = 1700000000.125678;
        $this->duration = $this->endTime - $this->startTime;
        
        $this->successStamp = new TimingStamp(
            startTime: $this->startTime,
            endTime: $this->endTime,
            duration: $this->duration,
            operationName: 'test_op',
            success: true
        );

        $this->failureStamp = new TimingStamp(
            startTime: $this->startTime,
            endTime: $this->endTime,
            duration: $this->duration,
            operationName: 'failed_op',
            success: false,
            error: 'Test error'
        );
    });

    it('calculates duration in different units', function () {
        expect($this->successStamp->durationMs())->toBeCloseTo(2.222, 2);
        expect($this->successStamp->durationMicros())->toBeCloseTo(2222, 0);
    });

    it('formats duration appropriately', function () {
        // Test microsecond formatting (< 1ms)
        $microStamp = new TimingStamp(0, 0, 0.0005, 'micro_op'); // 0.5ms = 500μs
        expect($microStamp->durationFormatted())->toBe('500μs');

        // Test millisecond formatting (< 1s)
        expect($this->successStamp->durationFormatted())->toBe('2.22ms');

        // Test second formatting (>= 1s)
        $longStamp = new TimingStamp(0, 0, 1.5, 'long_op');
        expect($longStamp->durationFormatted())->toBe('1.500s');
    });

    it('creates DateTime objects correctly', function () {
        $startDateTime = $this->successStamp->startDateTime();
        $endDateTime = $this->successStamp->endDateTime();

        expect($startDateTime)->toBeInstanceOf(\DateTimeImmutable::class);
        expect($endDateTime)->toBeInstanceOf(\DateTimeImmutable::class);
        
        // The timestamps should be different (endTime > startTime)
        expect($endDateTime->format('U.u'))->toBeGreaterThan($startDateTime->format('U.u'));
    });

    it('provides success/failure status', function () {
        expect($this->successStamp->isSuccess())->toBeTrue();
        expect($this->successStamp->isFailure())->toBeFalse();

        expect($this->failureStamp->isSuccess())->toBeFalse();
        expect($this->failureStamp->isFailure())->toBeTrue();
    });

    it('generates summary strings', function () {
        $successSummary = $this->successStamp->summary();
        expect($successSummary)->toContain('test_op');
        expect($successSummary)->toContain('SUCCESS');
        expect($successSummary)->toContain('2.22ms');

        $failureSummary = $this->failureStamp->summary();
        expect($failureSummary)->toContain('failed_op');
        expect($failureSummary)->toContain('FAILED');
        expect($failureSummary)->toContain('Test error');
    });

    it('converts to array correctly', function () {
        $array = $this->successStamp->toArray();

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

    it('creates stamps with static factory methods', function () {
        $successStamp = TimingStamp::success(1.0, 2.5, 'success_op');
        expect($successStamp->operationName)->toBe('success_op');
        expect($successStamp->isSuccess())->toBeTrue();
        expect($successStamp->duration)->toBe(1.5);

        $failureStamp = TimingStamp::failure(1.0, 2.0, 'Error occurred', 'failure_op');
        expect($failureStamp->operationName)->toBe('failure_op');
        expect($failureStamp->isFailure())->toBeTrue();
        expect($failureStamp->error)->toBe('Error occurred');
        expect($failureStamp->duration)->toBe(1.0);
    });
});