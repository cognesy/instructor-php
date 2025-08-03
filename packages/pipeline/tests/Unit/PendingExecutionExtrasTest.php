<?php declare(strict_types=1);

use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PendingExecution;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\TagInterface;

class ExecutionTag implements TagInterface {
    public function __construct(public readonly string $step) {}
}

describe('PendingExecution Enhanced Operations', function () {

    describe('lazy evaluation behavior', function () {
        it('defers execution until value is requested', function () {
            $executed = false;
            
            $pending = Pipeline::for(10)
                ->through(function($x) use (&$executed) {
                    $executed = true;
                    return $x * 2;
                })
                ->create();
            
            expect($executed)->toBeFalse();
            
            $value = $pending->value();
            expect($executed)->toBeTrue();
            expect($value)->toBe(20);
        });

        it('caches results after first execution', function () {
            $executeCount = 0;
            
            $pending = Pipeline::for(10)
                ->through(function($x) use (&$executeCount) {
                    $executeCount++;
                    return $x * 2;
                })
                ->create();
            
            $value1 = $pending->value();
            $value2 = $pending->value();
            $state = $pending->state();
            
            expect($executeCount)->toBe(1);
            expect($value1)->toBe($value2);
        });
    });

    describe('monadic composition integration', function () {
        it('composes with ProcessingState monadic operations', function () {
            $pending = Pipeline::for(10)
                ->through(fn($x) => $x * 2)
                ->create();
            
            $result = $pending->state()
                ->map(fn($x) => $x + 5)
                ->filter(fn($x) => $x > 15);
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(25);
        });

        it('preserves pipeline execution state in monadic operations', function () {
            $pending = Pipeline::for(10)
                ->withTags(new ExecutionTag('pipeline'))
                ->through(fn($x) => $x * 2)
                ->create();
            
            $state = $pending->state();
            $result = $state
                ->map(fn($x) => $x + 5)
                ->withTags(new ExecutionTag('monadic'));
            
            $tags = $result->allTags(ExecutionTag::class);
            expect($tags)->toHaveCount(2);
            expect($tags[0]->step)->toBe('pipeline');
            expect($tags[1]->step)->toBe('monadic');
        });
    });

    describe('error handling in lazy context', function () {
        it('handles pipeline errors lazily', function () {
            $pending = Pipeline::for(10)
                ->through(fn($x) => throw new \RuntimeException('Pipeline error'))
                ->create();
            
            // Error not thrown until execution
            $result = $pending->state();
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('Pipeline error');
        });

        it('combines pipeline and monadic errors correctly', function () {
            $pending = Pipeline::for(10)
                ->through(fn($x) => $x * 2)
                ->create();
            
            $result = $pending->state()
                ->map(fn($x) => $x + 5)
                ->filter(fn($x) => $x > 100, 'Value too small'); // Will fail
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('Value too small');
        });
    });

    describe('stream operations', function () {
        it('works with monadic transformations', function () {
            $pending = Pipeline::for([1, 2, 3])
                ->create();
            
            $state = $pending->state()
                ->map(fn($array) => array_map(fn($x) => $x * 2, $array));
            
            $stream = $pending->for($state->value())->stream();
            $results = iterator_to_array($stream);
            
            expect($results)->toBe([2, 4, 6]);
        });

        it('handles empty results gracefully', function () {
            $pending = Pipeline::for([1, 2, 3])
                ->create();
            
            $state = $pending->state()
                ->filter(fn($array) => count($array) > 5); // Will fail
            
            $stream = $pending->for($state->valueOr([]))->stream();
            $results = iterator_to_array($stream);
            
            expect($results)->toBeEmpty();
        });
    });

    describe('value extraction patterns', function () {
        it('extracts value after monadic operations', function () {
            $pending = Pipeline::for(10)
                ->through(fn($x) => $x * 2)
                ->create();
            
            $finalValue = $pending->state()
                ->map(fn($x) => $x + 5)
                ->value();
            
            expect($finalValue)->toBe(25);
        });

        it('extracts valueOr with default after failure', function () {
            $pending = Pipeline::for(10)
                ->through(fn($x) => $x * 2)
                ->create();
            
            $finalValue = $pending->state()
                ->filter(fn($x) => $x > 100)
                ->valueOr(42);
            
            expect($finalValue)->toBe(42);
        });

        it('extracts result for full monadic control', function () {
            $pending = Pipeline::for(10)
                ->through(fn($x) => $x * 2)
                ->create();
            
            $result = $pending->state()
                ->map(fn($x) => $x + 5)
                ->result();
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->unwrap())->toBe(25);
        });
    });

    describe('batch processing patterns', function () {
        it('processes multiple values with same pipeline', function () {
            $pipeline = Pipeline::for(0)
                ->through(fn($x) => $x * 2)
                ->through(fn($x) => $x + 1)
                ->create();
            
            $results = [];
            foreach ([1, 2, 3] as $input) {
                $result = $pipeline->for($input)->state()
                    ->map(fn($x) => $x * 10)
                    ->value();
                $results[] = $result;
            }
            
            expect($results)->toBe([30, 50, 70]);
        });

        it('handles mixed success/failure in batch', function () {
            $pipeline = Pipeline::for(0)
                ->through(fn($x) => $x > 0 ? $x * 2 : throw new \Exception('Negative'))
                ->create();
            
            $results = [];
            foreach ([-1, 2, -3, 4] as $input) {
                $state = $pipeline->for($input)->state()
                    ->map(fn($x) => $x + 10);
                    
                $results[] = $state->isSuccess() ? $state->value() : 'error';
            }
            
            expect($results)->toBe(['error', 14, 'error', 18]);
        });
    });

    describe('performance characteristics', function () {
        it('maintains lazy evaluation with monadic chains', function () {
            $pipelineExecuted = false;
            $monadicExecuted = false;
            
            $pending = Pipeline::for(10)
                ->through(function($x) use (&$pipelineExecuted) {
                    $pipelineExecuted = true;
                    return $x * 2;
                })
                ->create();
            
            // Pipeline hasn't executed yet
            expect($pipelineExecuted)->toBeFalse();
            
            // Get state (this executes the pipeline)
            $state = $pending->state();
            expect($pipelineExecuted)->toBeTrue();
            
            // Now apply monadic operations (these are immediate on ProcessingState)
            $chained = $state->map(function($x) use (&$monadicExecuted) {
                $monadicExecuted = true;
                return $x + 5;
            });
            
            expect($monadicExecuted)->toBeTrue(); // ProcessingState operations are immediate
            expect($chained->value())->toBe(25);
        });
    });
});