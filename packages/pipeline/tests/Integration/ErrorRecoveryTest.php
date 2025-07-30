<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Pipeline\TagInterface;
use Cognesy\Pipeline\Tags\ErrorTag;
use Cognesy\Utils\Result\Result;

// Test tags for error scenario tracking
class ErrorContextTag implements TagInterface
{
    public function __construct(
        public readonly string $context,
        public readonly ?string $recoveryAction = null
    ) {}
}

class CircuitBreakerTag implements TagInterface
{
    public function __construct(
        public readonly string $state, // 'closed', 'open', 'half-open'
        public readonly int $failureCount = 0
    ) {}
}

// Test middleware for error scenarios
class ErrorRecoveryMiddleware implements PipelineMiddlewareInterface
{
    public function handle(Computation $computation, callable $next): Computation
    {
        $result = $next($computation);
        
        if ($result->isFailure()) {
            // Attempt recovery by providing default value
            $recoveryTag = new ErrorContextTag('auto-recovery', 'default-fallback');
            return $result
                ->with($recoveryTag)
                ->withResult(Result::success('recovered-value'));
        }
        
        return $result;
    }
}

class CircuitBreakerMiddleware implements PipelineMiddlewareInterface
{
    private static int $failureCount = 0;
    private static string $state = 'closed'; // closed, open, half-open
    
    public function __construct(private int $threshold = 3) {}
    
    public function handle(Computation $computation, callable $next): Computation
    {
        $tagged = $computation->with(new CircuitBreakerTag(self::$state, self::$failureCount));
        
        // Circuit is open - fail fast
        if (self::$state === 'open') {
            return $tagged->withResult(Result::failure(new RuntimeException('Circuit breaker open')));
        }
        
        $result = $next($tagged);
        
        if ($result->isFailure()) {
            self::$failureCount++;
            if (self::$failureCount >= $this->threshold) {
                self::$state = 'open';
            }
        } else {
            self::$failureCount = 0;
            self::$state = 'closed';
        }
        
        return $result;
    }
    
    public static function reset(): void
    {
        self::$failureCount = 0;
        self::$state = 'closed';
    }
}

describe('Error Recovery and Edge Cases Integration Tests', function () {
    beforeEach(function () {
        CircuitBreakerMiddleware::reset();
    });

    describe('Error Propagation and Context', function () {
        it('preserves error context through pipeline stages', function () {
            $errorContextMiddleware = new class implements PipelineMiddlewareInterface {
                public function handle(Computation $computation, callable $next): Computation {
                    $result = $next($computation);
                    
                    if ($result->isFailure()) {
                        return $result->with(new ErrorContextTag('stage1-failure', 'logged'));
                    }
                    
                    return $result;
                }
            };
            
            $result = Pipeline::for('input')
                ->withMiddleware($errorContextMiddleware)
                ->through(function($x) {
                    throw new InvalidArgumentException("Stage 1 failed: $x");
                })
                ->through(function($x) {
                    // This should not execute due to failure
                    return "stage2-$x";
                })
                ->process();
            
            expect($result->isSuccess())->toBeFalse();
            expect($result->exception())->toBeInstanceOf(InvalidArgumentException::class);
            expect($result->exception()->getMessage())->toBe('Stage 1 failed: input');
            
            $computation = $result->computation();
            expect($computation->has(ErrorContextTag::class))->toBeTrue();
            expect($computation->has(ErrorTag::class))->toBeTrue();
            
            $errorContext = $computation->first(ErrorContextTag::class);
            expect($errorContext->context)->toBe('stage1-failure');
            expect($errorContext->recoveryAction)->toBe('logged');
        });

        it('accumulates multiple error contexts', function () {
            $contextMiddleware = new class implements PipelineMiddlewareInterface {
                public function handle(Computation $computation, callable $next): Computation {
                    $withPreContext = $computation->with(new ErrorContextTag('pre-processing', 'validated'));
                    $result = $next($withPreContext);
                    
                    if ($result->isFailure()) {
                        return $result->with(new ErrorContextTag('post-failure', 'notified'));
                    }
                    
                    return $result;
                }
            };
            
            $result = Pipeline::for('test')
                ->withMiddleware($contextMiddleware)
                ->through(function($x) {
                    throw new RuntimeException('Processing failed');
                })
                ->process();
            
            $computation = $result->computation();
            $contexts = $computation->all(ErrorContextTag::class);
            
            expect(count($contexts))->toBe(2);
            expect($contexts[0]->context)->toBe('pre-processing');
            expect($contexts[1]->context)->toBe('post-failure');
        });
    });

    describe('Null Handling Strategies', function () {
        it('handles null with different strategies across processors', function () {
            // Test NullStrategy::Allow
            $result1 = Pipeline::for('start')
                ->through(fn($x) => null, NullStrategy::Allow)
                ->through(fn($x) => $x ?? 'fallback', NullStrategy::Allow)
                ->process();
            
            expect($result1->isSuccess())->toBeTrue();
            expect($result1->value())->toBe('fallback');
            
            // Test NullStrategy::Fail
            $result2 = Pipeline::for('start')
                ->through(fn($x) => null, NullStrategy::Fail)
                ->through(fn($x) => "never-reached-$x")
                ->process();
            
            expect($result2->isSuccess())->toBeFalse();
            expect($result2->exception()->getMessage())->toContain('null');
        });

        it('handles null in complex processing chains', function () {
            $result = Pipeline::for(['data' => null])
                ->through(fn($x) => $x['data'], NullStrategy::Allow)
                ->when(fn($c) => $c->result()->unwrap() === null, fn($x) => 'default')
                ->through(fn($x) => strtoupper($x))
                ->process();
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe('DEFAULT');
        });
    });

    describe('Error Recovery Patterns', function () {
        it('implements automatic error recovery', function () {
            $attempts = 0;
            
            $result = Pipeline::for('data')
                ->withMiddleware(new ErrorRecoveryMiddleware())
                ->through(function($x) use (&$attempts) {
                    $attempts++;
                    throw new RuntimeException("Failed attempt $attempts");
                })
                ->process();
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe('recovered-value');
            expect($attempts)->toBe(1);
            
            $computation = $result->computation();
            expect($computation->has(ErrorContextTag::class))->toBeTrue();
            expect($computation->first(ErrorContextTag::class)->recoveryAction)->toBe('default-fallback');
        });

        it('implements circuit breaker pattern', function () {
            $pipeline = Pipeline::for('data')
                ->withMiddleware(new CircuitBreakerMiddleware(2))
                ->through(fn($x) => throw new RuntimeException('Service unavailable'));
            
            // First failure
            $result1 = $pipeline->process();
            expect($result1->isSuccess())->toBeFalse();
            expect($result1->computation()->first(CircuitBreakerTag::class)->state)->toBe('closed');
            expect($result1->computation()->first(CircuitBreakerTag::class)->failureCount)->toBe(0);
            
            // Second failure - should open circuit after this failure
            $result2 = $pipeline->process();
            expect($result2->isSuccess())->toBeFalse();
            expect($result2->computation()->first(CircuitBreakerTag::class)->state)->toBe('closed');
            expect($result2->computation()->first(CircuitBreakerTag::class)->failureCount)->toBe(1);
            
            // Third attempt - circuit should now be open
            $result3 = $pipeline->process();
            expect($result3->isSuccess())->toBeFalse();
            expect($result3->exception()->getMessage())->toBe('Circuit breaker open');
        });
    });

    describe('Resource Exhaustion Scenarios', function () {
        it('handles large data processing gracefully', function () {
            $largeData = array_fill(0, 10000, 'item');
            
            $result = Pipeline::for($largeData)
                ->through(function($items) {
                    // Simulate memory-intensive operation
                    return array_map(fn($item) => strtoupper($item), $items);
                })
                ->through(function($items) {
                    // Simulate another processing step
                    return array_slice($items, 0, 5); // Take first 5 items
                })
                ->process();
            
            expect($result->isSuccess())->toBeTrue();
            expect(count($result->value()))->toBe(5);
            expect($result->value()[0])->toBe('ITEM');
        });

        it('handles processing timeout simulation', function () {
            $timeoutMiddleware = new class implements PipelineMiddlewareInterface {
                public function handle(Computation $computation, callable $next): Computation {
                    $startTime = microtime(true);
                    $result = $next($computation);
                    $duration = microtime(true) - $startTime;
                    
                    // Simulate timeout if processing takes too long
                    if ($duration > 0.001) { // 1ms timeout for test
                        return $computation->withResult(
                            Result::failure(new RuntimeException('Operation timeout'))
                        );
                    }
                    
                    return $result;
                }
            };
            
            // Fast operation - should succeed
            $result1 = Pipeline::for('fast')
                ->withMiddleware($timeoutMiddleware)
                ->through(fn($x) => $x)
                ->process();
            
            expect($result1->isSuccess())->toBeTrue();
            
            // Slow operation - should timeout
            $result2 = Pipeline::for('slow')
                ->withMiddleware($timeoutMiddleware)
                ->through(function($x) {
                    usleep(2000); // 2ms delay
                    return $x;
                })
                ->process();
            
            expect($result2->isSuccess())->toBeFalse();
            expect($result2->exception()->getMessage())->toBe('Operation timeout');
        });
    });

    describe('Concurrent Processing Edge Cases', function () {
        it('handles state isolation between pipeline instances', function () {
            $sharedCounter = 0;
            
            $pipeline = Pipeline::for(1)
                ->through(function($x) use (&$sharedCounter) {
                    $sharedCounter++;
                    return $x + $sharedCounter;
                });
            
            // Execute multiple times
            $result1 = $pipeline->process();
            $result2 = $pipeline->process();
            $result3 = $pipeline->process();
            
            expect($result1->value())->toBe(2); // 1 + 1
            expect($result2->value())->toBe(3); // 1 + 2
            expect($result3->value())->toBe(4); // 1 + 3
            
            // Each execution should be independent
            expect($sharedCounter)->toBe(3);
        });

        it('handles computation immutability under concurrent modifications', function () {
            $baseComputation = Computation::wrap('base', [new ErrorContextTag('initial')]);
            
            // Simulate concurrent modifications
            $modified1 = $baseComputation->with(new ErrorContextTag('branch1'));
            $modified2 = $baseComputation->with(new ErrorContextTag('branch2'));
            
            // Original should be unchanged
            expect($baseComputation->count(ErrorContextTag::class))->toBe(1);
            expect($baseComputation->first(ErrorContextTag::class)->context)->toBe('initial');
            
            // Modifications should be independent
            expect($modified1->count(ErrorContextTag::class))->toBe(2);
            expect($modified2->count(ErrorContextTag::class))->toBe(2);
            
            $modified1Contexts = $modified1->all(ErrorContextTag::class);
            $modified2Contexts = $modified2->all(ErrorContextTag::class);
            
            expect($modified1Contexts[1]->context)->toBe('branch1');
            expect($modified2Contexts[1]->context)->toBe('branch2');
        });
    });

    describe('Complex Error Scenarios', function () {
        it('handles nested pipeline failures', function () {
            $innerPipeline = Pipeline::for('inner-data')
                ->through(fn($x) => throw new InvalidArgumentException('Inner failure'));
            
            $result = Pipeline::for('outer-data')
                ->through(function($x) use ($innerPipeline) {
                    $innerResult = $innerPipeline->process();
                    if (!$innerResult->isSuccess()) {
                        throw new RuntimeException('Outer failure: ' . $innerResult->exception()->getMessage());
                    }
                    return $innerResult->value();
                })
                ->process();
            
            expect($result->isSuccess())->toBeFalse();
            expect($result->exception()->getMessage())->toBe('Outer failure: Inner failure');
        });

        it('handles error recovery with partial success', function () {
            $items = ['good1', 'bad', 'good2', 'bad', 'good3'];
            $processed = [];
            $errors = [];
            
            $result = Pipeline::for($items)
                ->through(function($items) use (&$processed, &$errors) {
                    foreach ($items as $item) {
                        try {
                            if ($item === 'bad') {
                                throw new RuntimeException("Cannot process: $item");
                            }
                            $processed[] = strtoupper($item);
                        } catch (Exception $e) {
                            $errors[] = $e->getMessage();
                        }
                    }
                    return $processed;
                })
                ->process();
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(['GOOD1', 'GOOD2', 'GOOD3']);
            expect(count($errors))->toBe(2);
            expect($errors[0])->toBe('Cannot process: bad');
        });

        it('handles error tag accumulation in complex scenarios', function () {
            $errorHandlingMiddleware = new class implements PipelineMiddlewareInterface {
                public function handle(Computation $computation, callable $next): Computation {
                    $withValidation = $computation->with(new ErrorContextTag('validation', 'input-check'));
                    $result = $next($withValidation);
                    
                    if ($result->isFailure()) {
                        return $result->with(new ErrorContextTag('processing', 'length-validation-failed'));
                    }
                    
                    return $result;
                }
            };
            
            $result = Pipeline::for('test')
                ->withMiddleware($errorHandlingMiddleware)
                ->through(function($x) {
                    if (strlen($x) < 10) {
                        throw new InvalidArgumentException('Input too short');
                    }
                    return $x;
                })
                ->through(fn($x) => strtoupper($x)) // Should not execute
                ->process();
            
            expect($result->isSuccess())->toBeFalse();
            
            $computation = $result->computation();
            $contexts = $computation->all(ErrorContextTag::class);
            
            expect(count($contexts))->toBe(2);
            expect($contexts[0]->context)->toBe('validation');
            expect($contexts[1]->context)->toBe('processing');
            
            // Should also have ErrorTag from the exception
            expect($computation->has(ErrorTag::class))->toBeTrue();
        });
    });
});