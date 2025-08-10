<?php

use Cognesy\Pipeline\Middleware\Observation\StepMemory;
use Cognesy\Pipeline\Middleware\Observation\StepTiming;
use Cognesy\Pipeline\Middleware\Observation\TrackMemory;
use Cognesy\Pipeline\Middleware\Observation\TrackTime;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Processor\Call;
use Cognesy\Pipeline\Tag\Observation\MemoryTag;
use Cognesy\Pipeline\Tag\Observation\StepMemoryTag;
use Cognesy\Pipeline\Tag\Observation\StepTimingTag;
use Cognesy\Pipeline\Tag\Observation\TimingTag;

describe('TrackTime and Memory Tracking Integration', function () {
    
    it('captures pipeline-level timing data', function () {
        $result = Pipeline::empty()
            ->withMiddleware(TrackTime::capture('test-operation'))
            ->throughProcessor(Call::withValue(fn($x) => $x * 2))
            ->create()
            ->for(5)
            ->execute();

        $timings = $result->allTags(TimingTag::class);
        
        expect($timings)->toHaveCount(1);
        
        $timing = $timings[0];
        expect($timing->operationName)->toBe('test-operation');
        expect($timing->success)->toBeTrue();
        expect($timing->duration)->toBeGreaterThan(0);
        expect($timing->startTime)->toBeGreaterThan(0);
        expect($timing->endTime)->toBeGreaterThan($timing->startTime);
        expect($timing->durationMs())->toBeGreaterThan(0);
    });

    it('captures pipeline-level memory data', function () {
        $result = Pipeline::empty()
            ->withMiddleware(TrackMemory::capture('memory-test'))
            ->throughProcessor(Call::withValue(function($x) {
                // Allocate some memory to see usage
                $data = array_fill(0, 1000, 'test');
                return $x + count($data);
            }))
            ->create()
            ->for(10)
            ->execute();

        $memoryTags = $result->allTags(MemoryTag::class);
        
        expect($memoryTags)->toHaveCount(1);
        
        $memory = $memoryTags[0];
        expect($memory->operationName)->toBe('memory-test');
        expect($memory->startMemory)->toBeGreaterThan(0);
        expect($memory->endMemory)->toBeGreaterThan(0);
        expect($memory->memoryUsedMB())->toBeNumeric();
    });

    it('captures step-level timing data', function () {
        $result = Pipeline::empty()
            ->aroundEach(StepTiming::capture('all-steps'))  // Single hook for all processors
            ->throughProcessor(Call::withValue(fn($x) => $x + 1))
            ->throughProcessor(Call::withValue(fn($x) => $x * 3))
            ->create()
            ->for(2)
            ->execute();

        $stepTimings = $result->allTags(StepTimingTag::class);
        
        expect($stepTimings)->toHaveCount(2); // One timing per processor
        
        // Both should have the same step name since they use the same hook
        expect($stepTimings[0]->stepName)->toBe('all-steps');
        expect($stepTimings[0]->success)->toBeTrue();
        expect($stepTimings[0]->duration)->toBeGreaterThan(0);
        
        expect($stepTimings[1]->stepName)->toBe('all-steps');
        expect($stepTimings[1]->success)->toBeTrue();
        expect($stepTimings[1]->duration)->toBeGreaterThan(0);
    });

    it('captures step-level memory data', function () {
        $result = Pipeline::empty()
            ->aroundEach(StepMemory::capture('memory-step'))
            ->throughProcessor(Call::withValue(function($x) {
                $data = str_repeat('x', 1000);
                return $x . $data;
            }))
            ->create()
            ->for('test')
            ->execute();

        $stepMemory = $result->allTags(StepMemoryTag::class);
        
        expect($stepMemory)->toHaveCount(1);
        
        $memory = $stepMemory[0];
        expect($memory->stepName)->toBe('memory-step');
        expect($memory->startMemory)->toBeGreaterThan(0);
        expect($memory->endMemory)->toBeGreaterThan(0);
        expect($memory->memoryUsedFormatted())->toBeString();
    });

    it('combines timing and memory tracking', function () {
        $result = Pipeline::empty()
            ->withMiddleware(TrackTime::capture('full-pipeline'))
            ->withMiddleware(TrackMemory::capture('full-pipeline'))
            ->aroundEach(StepTiming::capture('multiply-step'))
            ->aroundEach(StepMemory::capture('multiply-step'))
            ->throughProcessor(Call::withValue(fn($x) => $x * 2))
            ->create()
            ->for(10)
            ->execute();

        // Check we have all expected tags
        expect($result->allTags(TimingTag::class))->toHaveCount(1);
        expect($result->allTags(MemoryTag::class))->toHaveCount(1);
        expect($result->allTags(StepTimingTag::class))->toHaveCount(1);
        expect($result->allTags(StepMemoryTag::class))->toHaveCount(1);

        // Verify result is correct
        expect($result->value())->toBe(20);
        expect($result->isSuccess())->toBeTrue();
    });

    it('tracks timing for failed operations', function () {
        $result = Pipeline::empty()
            ->withMiddleware(TrackTime::capture('failing-op'))
            ->aroundEach(StepTiming::capture('failing-step'))
            ->throughProcessor(Call::withValue(function($x) {
                throw new Exception('Test failure');
            }))
            ->create()
            ->for(5)
            ->execute();

        $timing = $result->allTags(TimingTag::class)[0];
        $stepTiming = $result->allTags(StepTimingTag::class)[0];
        
        expect($timing->success)->toBeFalse();
        expect($stepTiming->success)->toBeFalse();
        expect($timing->duration)->toBeGreaterThan(0);
        expect($stepTiming->duration)->toBeGreaterThan(0);
    });

    it('formats durations correctly', function () {
        $result = Pipeline::empty()
            ->withMiddleware(TrackTime::capture('format-test'))
            ->throughProcessor(Call::withValue(function($x) {
                // Small delay to get measurable timing
                usleep(1000); // 1ms
                return $x;
            }))
            ->create()
            ->for(1)
            ->execute();

        $timing = $result->allTags(TimingTag::class)[0];
        
        expect($timing->durationFormatted())->toBeString();
        expect($timing->durationMs())->toBeGreaterThan(0.5); // At least 0.5ms
        expect($timing->toArray())->toBeArray();
        expect($timing->toArray()['operation_name'])->toBe('format-test');
    });
    
});