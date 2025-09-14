<?php

use Cognesy\Pipeline\Operators\Call;
use Cognesy\Pipeline\Operators\Observation\StepMemory;
use Cognesy\Pipeline\Operators\Observation\StepTiming;
use Cognesy\Pipeline\Operators\Observation\TrackMemory;
use Cognesy\Pipeline\Operators\Observation\TrackTime;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\StepMemoryTag;
use Cognesy\Pipeline\Tag\StepTimingTag;
use Cognesy\Utils\TagMap\Tags\MemoryProfilerTag;
use Cognesy\Utils\TagMap\Tags\TimeProfilerTag;

describe('TrackTime and Memory Tracking Integration', function () {
    
    it('captures pipeline-level timing data', function () {
        $result = Pipeline::builder()
            ->withOperator(TrackTime::capture('test-operation'))
            ->throughOperator(Call::withValue(fn($x) => $x * 2))
            ->create()
            ->executeWith(ProcessingState::with(5))
            ->state();

        $timings = $result->allTags(TimeProfilerTag::class);
        
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
        $result = Pipeline::builder()
            ->withOperator(TrackMemory::capture('memory-test'))
            ->throughOperator(Call::withValue(function($x) {
                // Allocate some memory to see usage
                $data = array_fill(0, 1000, 'test');
                return $x + count($data);
            }))
            ->create()
            ->executeWith(ProcessingState::with(10))
            ->state();

        $memoryTags = $result->allTags(MemoryProfilerTag::class);
        
        expect($memoryTags)->toHaveCount(1);
        
        $memory = $memoryTags[0];
        expect($memory->operationName)->toBe('memory-test');
        expect($memory->startMemory)->toBeGreaterThan(0);
        expect($memory->endMemory)->toBeGreaterThan(0);
        expect($memory->memoryUsedMB())->toBeNumeric();
    });

    it('captures step-level timing data', function () {
        $result = Pipeline::builder()
            ->aroundEach(StepTiming::capture('all-steps'))  // Single hook for all processors
            ->throughOperator(Call::withValue(fn($x) => $x + 1))
            ->throughOperator(Call::withValue(fn($x) => $x * 3))
            ->create()
            ->executeWith(ProcessingState::with(2))
            ->state();

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
        $result = Pipeline::builder()
            ->aroundEach(StepMemory::capture('memory-step'))
            ->throughOperator(Call::withValue(function($x) {
                $data = str_repeat('x', 1000);
                return $x . $data;
            }))
            ->create()
            ->executeWith(ProcessingState::with('test'))
            ->state();

        $stepMemory = $result->allTags(StepMemoryTag::class);
        
        expect($stepMemory)->toHaveCount(1);
        
        $memory = $stepMemory[0];
        expect($memory->stepName)->toBe('memory-step');
        expect($memory->startMemory)->toBeGreaterThan(0);
        expect($memory->endMemory)->toBeGreaterThan(0);
        expect($memory->memoryUsedFormatted())->toBeString();
    });

    it('combines timing and memory tracking', function () {
        $result = Pipeline::builder()
            ->withOperator(TrackTime::capture('full-pipeline'))
            ->withOperator(TrackMemory::capture('full-pipeline'))
            ->aroundEach(StepTiming::capture('multiply-step'))
            ->aroundEach(StepMemory::capture('multiply-step'))
            ->throughOperator(Call::withValue(fn($x) => $x * 2))
            ->create()
            ->executeWith(ProcessingState::with(10))
            ->state();

        // Check we have all expected tags
        expect($result->allTags(TimeProfilerTag::class))->toHaveCount(1);
        expect($result->allTags(MemoryProfilerTag::class))->toHaveCount(1);
        expect($result->allTags(StepTimingTag::class))->toHaveCount(1);
        expect($result->allTags(StepMemoryTag::class))->toHaveCount(1);

        // Verify result is correct
        expect($result->value())->toBe(20);
        expect($result->isSuccess())->toBeTrue();
    });

    it('tracks timing for failed operations', function () {
        $result = Pipeline::builder()
            ->withOperator(TrackTime::capture('failing-op'))
            ->aroundEach(StepTiming::capture('failing-step'))
            ->throughOperator(Call::withValue(function($x) {
                throw new Exception('Test failure');
            }))
            ->create()
            ->executeWith(ProcessingState::with(5))
            ->state();

        $timing = $result->allTags(TimeProfilerTag::class)[0];
        $stepTiming = $result->allTags(StepTimingTag::class)[0];
        
        expect($timing->success)->toBeFalse();
        expect($stepTiming->success)->toBeFalse();
        expect($timing->duration)->toBeGreaterThan(0);
        expect($stepTiming->duration)->toBeGreaterThan(0);
    });

    it('formats durations correctly', function () {
        $result = Pipeline::builder()
            ->withOperator(TrackTime::capture('format-test'))
            ->throughOperator(Call::withValue(function($x) {
                // Small delay to get measurable timing
                usleep(1000); // 1ms
                return $x;
            }))
            ->create()
            ->executeWith(ProcessingState::with(1))
            ->state();

        $timing = $result->allTags(TimeProfilerTag::class)[0];
        
        expect($timing->durationFormatted())->toBeString();
        expect($timing->durationMs())->toBeGreaterThan(0.5); // At least 0.5ms
        expect($timing->toArray())->toBeArray();
        expect($timing->toArray()['operation_name'])->toBe('format-test');
    });
    
});