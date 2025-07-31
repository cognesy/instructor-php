<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\PendingExecution;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\TagInterface;
use Cognesy\Utils\Result\Result;

// Test tags for pending execution testing
class ExecutionTag implements TagInterface
{
    public function __construct(public readonly string $phase) {}
}

describe('PendingComputation Feature Tests', function () {
    it('supports lazy evaluation with complex transformations', function () {
        $executionCount = 0;
        
        $pending = new PendingExecution(function() use (&$executionCount) {
            $executionCount++;
            $computation = Computation::for(['count' => 5], [new ExecutionTag('initial')]);
            return $computation->withResult(Result::success(['count' => 10]));
        });

        // Transform without executing
        $mapped = $pending
            ->map(fn($data) => $data['count'] * 2)
            ->map(fn($count) => ['doubled' => $count]);

        expect($executionCount)->toBe(0); // Not executed yet

        // Execute and get result
        $result = $mapped->value();
        expect($result)->toBe(['doubled' => 20]);
        expect($executionCount)->toBe(1); // Executed once

        // Multiple calls use cached result
        $result2 = $mapped->value();
        expect($result2)->toBe(['doubled' => 20]);
        expect($executionCount)->toBe(1); // Still once
    });

    it('chains complex transformations with computation preservation', function () {
        $pending = new PendingExecution(function() {
            return Computation::for(100, [
                new ExecutionTag('start'),
                new ExecutionTag('initialized')
            ]);
        });

        $final = $pending
            ->mapComputation(fn($computation) => $computation->with(new ExecutionTag('processing')))
            ->map(fn($x) => $x / 2)
            ->mapComputation(fn($computation) => $computation->with(new ExecutionTag('completed')))
            ->then(fn($x) => $x + 25);

        $computation = $final->computation();
        
        expect($computation->result()->unwrap())->toBe(75); // (100 / 2) + 25
        expect($computation->count(ExecutionTag::class))->toBe(4);
        
        $phases = array_map(
            fn($tag) => $tag->phase,
            $computation->all(ExecutionTag::class)
        );
        expect($phases)->toBe(['start', 'initialized', 'processing', 'completed']);
    });

    it('handles failure propagation through transformation chain', function () {
        $pending = new PendingExecution(function() {
            return Computation::for(
                Result::failure(new Exception('Initial failure')),
                [new ExecutionTag('failed')]
            );
        });

        $transformed = $pending
            ->map(fn($x) => $x * 2) // Should not execute
            ->then(fn($x) => $x + 10); // Should not execute

        expect($transformed->isSuccess())->toBeFalse();
        expect($transformed->exception())->toBeInstanceOf(Exception::class);
        expect($transformed->exception()->getMessage())->toBe('Initial failure');
        
        // Tags are preserved even in failure
        $computation = $transformed->computation();
        expect($computation->has(ExecutionTag::class))->toBeTrue();
    });

    it('processes streams with complex data', function () {
        $pending = new PendingExecution(function() {
            return Computation::for([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
                ['id' => 3, 'name' => 'Charlie']
            ]);
        });

        $names = [];
        foreach ($pending->stream() as $user) {
            $names[] = $user['name'];
        }

        expect($names)->toBe(['Alice', 'Bob', 'Charlie']);
    });

    it('integrates with Pipeline for end-to-end processing', function () {
        $executionLog = [];
        
        $pending = Pipeline::for(['items' => [1, 2, 3, 4, 5]])
            ->through(function($data) use (&$executionLog) {
                $executionLog[] = 'processing';
                return array_map(fn($x) => $x * 2, $data['items']);
            })
            ->through(function($items) use (&$executionLog) {
                $executionLog[] = 'filtering';
                return array_filter($items, fn($x) => $x > 4);
            })
            ->withTag(new ExecutionTag('pipeline'))
            ->process();

        expect($executionLog)->toBe([]); // Nothing executed yet

        // Transform the pending execution
        $final = $pending
            ->map(fn($items) => array_sum($items))
            ->then(fn($sum) => "Total: $sum");

        expect($executionLog)->toBe([]); // Still not executed

        // Execute and verify
        $result = $final->value();
        expect($result)->toBe('Total: 24');
        expect($executionLog)->toBe(['processing', 'filtering']);

        // Verify tags are preserved
        $computation = $final->computation();
        expect($computation->has(ExecutionTag::class))->toBeTrue();
    });

    it('handles complex computation transformations', function () {
        $pending = new PendingExecution(function() {
            return Computation::for('hello', [new ExecutionTag('created')]);
        });

        $result = $pending
            ->mapComputation(function($computation) {
                // Add processing metadata
                return $computation
                    ->with(new ExecutionTag('uppercase'))
                    ->withResult(Result::success(strtoupper($computation->result()->unwrap())));
            })
            ->mapComputation(function($computation) {
                // Add more processing
                return $computation
                    ->with(new ExecutionTag('exclamation'))
                    ->withResult(Result::success($computation->result()->unwrap() . '!'));
            });

        $computation = $result->computation();
        
        expect($computation->result()->unwrap())->toBe('HELLO!');
        expect($computation->count(ExecutionTag::class))->toBe(3);
        
        $phases = array_map(
            fn($tag) => $tag->phase,
            $computation->all(ExecutionTag::class)
        );
        expect($phases)->toBe(['created', 'uppercase', 'exclamation']);
    });

    it('supports conditional execution patterns', function () {
        $executionCount = 0;
        
        $pending = new PendingExecution(function() use (&$executionCount) {
            $executionCount++;
            return Computation::for(42);
        });

        // Check success without executing full computation
        $isSuccessful = $pending->map(fn($x) => $x > 0)->isSuccess();
        expect($isSuccessful)->toBeTrue();
        expect($executionCount)->toBe(1);

        // Conditional processing based on success
        if ($isSuccessful) {
            $result = Pipeline::for(100)
                ->through(fn($x) => $x + $pending->value())
                ->process()
                ->value();
            
            expect($result)->toBe(142);
        }
        
        expect($executionCount)->toBe(1); // Cached result used
    });

    it('handles error recovery in transformation chains', function () {
        $pending = new PendingExecution(function() {
            return Computation::for(10);
        });

        // Test that we can detect when computation will fail
        $errorResult = $pending->map(function($x) {
            if ($x < 20) {
                throw new Exception('Value too small');
            }
            return $x * 2;
        });

        // The error should be captured in the success/failure check
        expect($errorResult->isSuccess())->toBeFalse();
        
        // The failure should contain our exception
        $failure = $errorResult->exception();
        expect($failure)->toBeInstanceOf(Exception::class);
        expect($failure->getMessage())->toBe('Value too small');
    });
});