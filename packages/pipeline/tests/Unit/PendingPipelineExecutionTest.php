<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\PendingExecution;
use Cognesy\Pipeline\Tag\TagInterface;
use Cognesy\Utils\Result\Result;

// Test tag for unit testing
class PendingTestTag implements TagInterface
{
    public function __construct(public readonly string $label) {}
}

describe('PendingPipelineExecution Unit Tests', function () {
    describe('Construction', function () {
        it('constructs with callable', function () {
            $pending = new PendingExecution(fn() => 'test');
            expect($pending)->toBeInstanceOf(PendingExecution::class);
        });

        it('does not execute computation on construction', function () {
            $executed = false;
            
            new PendingExecution(function() use (&$executed) {
                $executed = true;
                return 'test';
            });
            
            expect($executed)->toBeFalse();
        });
    });

    describe('Value Extraction', function () {
        it('extracts value from computation result', function () {
            $pending = new PendingExecution(function() {
                return Computation::for(42);
            });
            
            expect($pending->value())->toBe(42);
        });

        it('extracts value from direct Result', function () {
            $pending = new PendingExecution(function() {
                return Result::success('direct result');
            });
            
            expect($pending->value())->toBe('direct result');
        });

        it('returns raw value for non-computation/result', function () {
            $pending = new PendingExecution(function() {
                return ['raw' => 'data'];
            });
            
            expect($pending->value())->toBe(['raw' => 'data']);
        });

        it('returns null for failed computation', function () {
            $pending = new PendingExecution(function() {
                return Computation::for(Result::failure(new Exception('error')));
            });
            
            expect($pending->value())->toBeNull();
        });
    });

    describe('Result Extraction', function () {
        it('extracts Result from computation', function () {
            $pending = new PendingExecution(function() {
                return Computation::for('test data');
            });
            
            $result = $pending->result();
            expect($result)->toBeInstanceOf(Result::class);
            expect($result->unwrap())->toBe('test data');
            expect($result->isSuccess())->toBeTrue();
        });

        it('returns Result directly', function () {
            $originalResult = Result::success('direct');
            $pending = new PendingExecution(function() use ($originalResult) {
                return $originalResult;
            });
            
            expect($pending->result())->toBe($originalResult);
        });

        it('wraps non-result values in success Result', function () {
            $pending = new PendingExecution(function() {
                return 'wrapped';
            });
            
            $result = $pending->result();
            expect($result->unwrap())->toBe('wrapped');
            expect($result->isSuccess())->toBeTrue();
        });
    });

    describe('Computation Extraction', function () {
        it('returns computation directly', function () {
            $originalComputation = Computation::for('test', [new PendingTestTag('original')]);
            
            $pending = new PendingExecution(function() use ($originalComputation) {
                return $originalComputation;
            });
            
            $computation = $pending->computation();
            expect($computation)->toBe($originalComputation);
            expect($computation->has(PendingTestTag::class))->toBeTrue();
        });

        it('wraps non-computation results', function () {
            $pending = new PendingExecution(function() {
                return Result::success('to wrap');
            });
            
            $computation = $pending->computation();
            expect($computation)->toBeInstanceOf(Computation::class);
            expect($computation->result()->unwrap())->toBe('to wrap');
        });
    });

    describe('Success/Failure Checking', function () {
        it('returns true for successful computation', function () {
            $pending = new PendingExecution(function() {
                return Computation::for('success');
            });
            
            expect($pending->isSuccess())->toBeTrue();
        });

        it('returns false for failed computation', function () {
            $pending = new PendingExecution(function() {
                return Computation::for(Result::failure(new Exception('failed')));
            });
            
            expect($pending->isSuccess())->toBeFalse();
        });

        it('returns true for successful Result', function () {
            $pending = new PendingExecution(function() {
                return Result::success('ok');
            });
            
            expect($pending->isSuccess())->toBeTrue();
        });

        it('returns false for failed Result', function () {
            $pending = new PendingExecution(function() {
                return Result::failure(new Exception('error'));
            });
            
            expect($pending->isSuccess())->toBeFalse();
        });

        it('returns false when computation throws', function () {
            $pending = new PendingExecution(function() {
                throw new Exception('computation error');
            });
            
            expect($pending->isSuccess())->toBeFalse();
        });
    });

    describe('Failure Extraction', function () {
        it('returns null for successful computation', function () {
            $pending = new PendingExecution(function() {
                return Computation::for('success');
            });
            
            expect($pending->exception())->toBeNull();
        });

        it('returns error for failed computation', function () {
            $error = new Exception('computation error');
            $pending = new PendingExecution(function() use ($error) {
                return Computation::for(Result::failure($error));
            });
            
            expect($pending->exception())->toBe($error);
        });

        it('returns error for failed Result', function () {
            $error = new Exception('result error');
            $pending = new PendingExecution(function() use ($error) {
                return Result::failure($error);
            });
            
            expect($pending->exception())->toBe($error);
        });

        it('returns exception when computation throws', function () {
            $pending = new PendingExecution(function() {
                throw new Exception('thrown error');
            });
            
            $failure = $pending->exception();
            expect($failure)->toBeInstanceOf(Exception::class);
            expect($failure->getMessage())->toBe('thrown error');
        });
    });

    describe('Stream Processing', function () {
        it('streams iterable computation content', function () {
            $pending = new PendingExecution(function() {
                return Computation::for([1, 2, 3]);
            });
            
            $items = [];
            foreach ($pending->stream() as $item) {
                $items[] = $item;
            }
            
            expect($items)->toBe([1, 2, 3]);
        });

        it('streams single value as single item', function () {
            $pending = new PendingExecution(function() {
                return Computation::for('single');
            });
            
            $items = [];
            foreach ($pending->stream() as $item) {
                $items[] = $item;
            }
            
            expect($items)->toBe(['single']);
        });

        it('returns empty stream for failed result', function () {
            $pending = new PendingExecution(function() {
                return Computation::for(Result::failure(new Exception('error')));
            });
            
            $items = [];
            foreach ($pending->stream() as $item) {
                $items[] = $item;
            }
            
            expect($items)->toBe([]);
        });
    });

    describe('Transformations', function () {
        describe('map()', function () {
            it('transforms successful computation value', function () {
                $pending = new PendingExecution(function() {
                    return Computation::for(10, [new PendingTestTag('original')]);
                });
                
                $mapped = $pending->map(fn($x) => $x * 2);
                $computation = $mapped->computation();
                
                expect($computation->result()->unwrap())->toBe(20);
                expect($computation->has(PendingTestTag::class))->toBeTrue(); // Tags preserved
            });

            it('preserves failure in computation', function () {
                $pending = new PendingExecution(function() {
                    return Computation::for(Result::failure(new Exception('error')));
                });
                
                $mapped = $pending->map(fn($x) => $x * 2); // Should not execute
                
                expect($mapped->isSuccess())->toBeFalse();
                expect($mapped->exception()->getMessage())->toBe('error');
            });

            it('transforms direct Result', function () {
                $pending = new PendingExecution(function() {
                    return Result::success(5);
                });
                
                $mapped = $pending->map(fn($x) => $x + 3);
                
                expect($mapped->result()->unwrap())->toBe(8);
            });
        });

        describe('mapComputation()', function () {
            it('transforms computation directly', function () {
                $pending = new PendingExecution(function() {
                    return Computation::for('test');
                });
                
                $mapped = $pending->mapComputation(function($computation) {
                    return $computation
                        ->with(new PendingTestTag('transformed'))
                        ->withResult(Result::success(strtoupper($computation->result()->unwrap())));
                });
                
                $computation = $mapped->computation();
                expect($computation->result()->unwrap())->toBe('TEST');
                expect($computation->has(PendingTestTag::class))->toBeTrue();
            });

            it('wraps non-computation results', function () {
                $pending = new PendingExecution(function() {
                    return 'raw value';
                });
                
                $mapped = $pending->mapComputation(function($computation) {
                    return $computation->with(new PendingTestTag('wrapped'));
                });
                
                $computation = $mapped->computation();
                expect($computation->result()->unwrap())->toBe('raw value');
                expect($computation->has(PendingTestTag::class))->toBeTrue();
            });
        });

        describe('then()', function () {
            it('chains computation with successful computation', function () {
                $pending = new PendingExecution(function() {
                    return Computation::for(5, [new PendingTestTag('chained')]);
                });
                
                $chained = $pending->then(fn($x) => $x * 3);
                $computation = $chained->computation();
                
                expect($computation->result()->unwrap())->toBe(15);
                expect($computation->has(PendingTestTag::class))->toBeTrue(); // Tags preserved
            });

            it('short-circuits on failure', function () {
                $pending = new PendingExecution(function() {
                    return Computation::for(Result::failure(new Exception('failed')));
                });
                
                $chained = $pending->then(fn($x) => $x * 3); // Should not execute
                
                expect($chained->isSuccess())->toBeFalse();
                expect($chained->exception()->getMessage())->toBe('failed');
            });
        });
    });

    describe('Lazy Evaluation', function () {
        it('executes computation only once', function () {
            $executionCount = 0;
            
            $pending = new PendingExecution(function() use (&$executionCount) {
                $executionCount++;
                return 'result';
            });
            
            // Multiple accesses
            $pending->value();
            $pending->result();
            $pending->computation();
            $pending->isSuccess();
            
            expect($executionCount)->toBe(1);
        });

        it('caches result across transformations', function () {
            $executionCount = 0;
            
            $pending = new PendingExecution(function() use (&$executionCount) {
                $executionCount++;
                return 10;
            });
            
            $mapped = $pending->map(fn($x) => $x * 2);
            
            // Access multiple times
            $mapped->value();
            $mapped->value();
            
            expect($executionCount)->toBe(1);
        });

        it('creates new execution context for transformations', function () {
            $baseExecutions = 0;
            $transformExecutions = 0;
            
            $base = new PendingExecution(function() use (&$baseExecutions) {
                $baseExecutions++;
                return 5;
            });
            
            $transformed = $base->map(function($x) use (&$transformExecutions) {
                $transformExecutions++;
                return $x * 2;
            });
            
            // Execute base only
            $base->value();
            expect($baseExecutions)->toBe(1);
            expect($transformExecutions)->toBe(0);
            
            // Execute transformed
            $transformed->value();
            expect($baseExecutions)->toBe(1); // Base used cached result
            expect($transformExecutions)->toBe(1);
        });
    });
});