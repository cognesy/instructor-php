<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Pipeline\TagInterface;
use Cognesy\Pipeline\Middleware\TimingMiddleware;
use Cognesy\Pipeline\Tags\TimingTag;
use Cognesy\Utils\Result\Result;

// Test middleware implementations for integration testing
class IntegrationTraceTag implements TagInterface
{
    public function __construct(public readonly string $traceId) {}
}

class IntegrationRetryTag implements TagInterface
{
    public function __construct(public readonly int $attempt, public readonly ?string $lastError = null) {}
}

class IntegrationCacheTag implements TagInterface
{
    public function __construct(public readonly string $key, public readonly bool $hit) {}
}

class TracingMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(private string $traceId) {}

    public function handle(Computation $computation, callable $next): Computation
    {
        $traced = $computation->with(new IntegrationTraceTag($this->traceId));
        return $next($traced);
    }
}

class RetryMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(private int $maxAttempts = 3) {}

    public function handle(Computation $computation, callable $next): Computation
    {
        $lastError = null;
        
        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            $tagged = $computation->with(new IntegrationRetryTag($attempt, $lastError));
            $result = $next($tagged);
            
            if ($result->isSuccess()) {
                return $result;
            }
            
            $lastError = $result->result()->error()?->getMessage() ?? 'Unknown error';
        }
        
        return $computation->with(new IntegrationRetryTag($this->maxAttempts, $lastError));
    }
}

class CacheMiddleware implements PipelineMiddlewareInterface
{
    private static array $cache = [];
    
    public function __construct(private string $keyPrefix = '') {}

    public function handle(Computation $computation, callable $next): Computation
    {
        $cacheKey = $this->keyPrefix . ':' . md5(serialize($computation->result()->unwrap()));
        
        // Check cache
        if (isset(self::$cache[$cacheKey])) {
            return $computation
                ->with(new IntegrationCacheTag($cacheKey, true))
                ->withResult(Result::success(self::$cache[$cacheKey]));
        }
        
        // Execute and cache result
        $result = $next($computation->with(new IntegrationCacheTag($cacheKey, false)));
        
        if ($result->isSuccess()) {
            self::$cache[$cacheKey] = $result->result()->unwrap();
        }
        
        return $result;
    }
    
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}

describe('Middleware Interaction Integration Tests', function () {
    beforeEach(function () {
        CacheMiddleware::clearCache();
    });

    describe('Middleware Execution Order', function () {
        it('executes middleware in correct order (outer to inner)', function () {
            $executionOrder = [];
            
            $middleware1 = new class($executionOrder) implements PipelineMiddlewareInterface {
                public function __construct(private array &$order) {}
                
                public function handle(Computation $computation, callable $next): Computation {
                    $this->order[] = 'M1_before';
                    $result = $next($computation);
                    $this->order[] = 'M1_after';
                    return $result;
                }
            };
            
            $middleware2 = new class($executionOrder) implements PipelineMiddlewareInterface {
                public function __construct(private array &$order) {}
                
                public function handle(Computation $computation, callable $next): Computation {
                    $this->order[] = 'M2_before';
                    $result = $next($computation);
                    $this->order[] = 'M2_after';
                    return $result;
                }
            };
            
            Pipeline::for('test')
                ->withMiddleware($middleware1, $middleware2)
                ->through(function($x) use (&$executionOrder) {
                    $executionOrder[] = 'PROCESSOR';
                    return $x;
                })
                ->process()
                ->value();
            
            expect($executionOrder)->toBe([
                'M1_before',
                'M2_before', 
                'PROCESSOR',
                'M2_after',
                'M1_after'
            ]);
        });

        it('prepended middleware executes first', function () {
            $executionOrder = [];
            
            $middleware1 = new class('SECOND', $executionOrder) implements PipelineMiddlewareInterface {
                public function __construct(private string $name, private array &$order) {}
                
                public function handle(Computation $computation, callable $next): Computation {
                    $this->order[] = $this->name;
                    return $next($computation);
                }
            };
            
            $middleware2 = new class('FIRST', $executionOrder) implements PipelineMiddlewareInterface {
                public function __construct(private string $name, private array &$order) {}
                
                public function handle(Computation $computation, callable $next): Computation {
                    $this->order[] = $this->name;
                    return $next($computation);
                }
            };
            
            $result = Pipeline::for('test')
                ->withMiddleware($middleware1)
                ->prependMiddleware($middleware2)
                ->through(fn($x) => $x)
                ->process();
            
            // Force execution by accessing result
            $result->value();
            
            expect($executionOrder)->toBe(['FIRST', 'SECOND']);
        });
    });

    describe('Real-World Middleware Combinations', function () {
        it('combines timing, tracing, and retry middleware', function () {
            $failCount = 0;
            
            $result = Pipeline::for('data')
                ->withMiddleware(
                    new TracingMiddleware('trace-123'),
                    new TimingMiddleware('operation'),
                    new RetryMiddleware(3)
                )
                ->through(function($x) use (&$failCount) {
                    $failCount++;
                    if ($failCount <= 2) {
                        throw new RuntimeException("Attempt $failCount failed");
                    }
                    return strtoupper($x);
                })
                ->process();
            
            $computation = $result->computation();
            
            // Should succeed after retries
            expect($result->success())->toBeTrue();
            expect($result->value())->toBe('DATA');
            expect($failCount)->toBe(3);
            
            // Should have trace tags
            expect($computation->has(IntegrationTraceTag::class))->toBeTrue();
            expect($computation->first(IntegrationTraceTag::class)->traceId)->toBe('trace-123');
            
            // Should have retry tags (at least the successful attempt)
            expect($computation->count(IntegrationRetryTag::class))->toBeGreaterThanOrEqual(1);
            expect($computation->last(IntegrationRetryTag::class)->attempt)->toBe(3);
            
            // Should have timing tags (multiple from retries)
            expect($computation->count(TimingTag::class))->toBeGreaterThan(0);
        });

        it('combines caching with timing middleware', function () {
            $processorExecutions = 0;
            
            $pipeline = Pipeline::for('expensive-operation')
                ->withMiddleware(
                    new CacheMiddleware('test'),
                    new TimingMiddleware('cached-op')
                )
                ->through(function($x) use (&$processorExecutions) {
                    $processorExecutions++;
                    usleep(1000); // Simulate expensive operation
                    return "processed-$x";
                });
            
            // First execution - cache miss
            $result1 = $pipeline->process();
            expect($result1->value())->toBe('processed-expensive-operation');
            expect($processorExecutions)->toBe(1);
            
            $computation1 = $result1->computation();
            expect($computation1->first(IntegrationCacheTag::class)->hit)->toBeFalse();
            expect($computation1->has(TimingTag::class))->toBeTrue();
            
            // Second execution - cache hit
            $result2 = $pipeline->process();
            expect($result2->value())->toBe('processed-expensive-operation');
            expect($processorExecutions)->toBe(1); // No additional execution
            
            $computation2 = $result2->computation();
            expect($computation2->first(IntegrationCacheTag::class)->hit)->toBeTrue();
        });
    });

    describe('Middleware Error Propagation', function () {
        it('handles middleware failure correctly', function () {
            $failingMiddleware = new class implements PipelineMiddlewareInterface {
                public function handle(Computation $computation, callable $next): Computation {
                    throw new RuntimeException('Middleware failure');
                }
            };
            
            $result = Pipeline::for('test')
                ->withMiddleware($failingMiddleware)
                ->through(fn($x) => $x)
                ->process();
            
            expect($result->success())->toBeFalse();
            expect($result->failure())->toBeInstanceOf(RuntimeException::class);
            expect($result->failure()->getMessage())->toBe('Middleware failure');
        });

        it('allows middleware to transform failures', function () {
            $errorTransformMiddleware = new class implements PipelineMiddlewareInterface {
                public function handle(Computation $computation, callable $next): Computation {
                    $result = $next($computation);
                    
                    if ($result->isFailure()) {
                        $originalError = $result->result()->error();
                        $transformedError = new RuntimeException(
                            "Transformed: " . $originalError->getMessage(),
                            0,
                            $originalError
                        );
                        return $result->withResult(Result::failure($transformedError));
                    }
                    
                    return $result;
                }
            };
            
            $result = Pipeline::for('test')
                ->withMiddleware($errorTransformMiddleware)
                ->through(fn($x) => throw new RuntimeException('Original error'))
                ->process();
            
            expect($result->success())->toBeFalse();
            expect($result->failure()->getMessage())->toBe('Transformed: Original error');
        });
    });

    describe('Tag Accumulation and Conflicts', function () {
        it('accumulates tags from multiple middleware correctly', function () {
            $result = Pipeline::for('test')
                ->withMiddleware(
                    new TracingMiddleware('trace-1'),
                    new TracingMiddleware('trace-2'),
                    new TimingMiddleware('step1'),
                    new TimingMiddleware('step2')
                )
                ->through(fn($x) => $x)
                ->process();
            
            $computation = $result->computation();
            
            // Should have multiple trace tags
            expect($computation->count(IntegrationTraceTag::class))->toBe(2);
            
            // Should have multiple timing tags  
            expect($computation->count(TimingTag::class))->toBe(2);
            
            // Tags should be in order of middleware execution
            $traceTags = $computation->all(IntegrationTraceTag::class);
            expect($traceTags[0]->traceId)->toBe('trace-1');
            expect($traceTags[1]->traceId)->toBe('trace-2');
        });

        it('handles same middleware type multiple times', function () {
            $result = Pipeline::for(1)
                ->withMiddleware(new TimingMiddleware('outer'))
                ->through(fn($x) => $x + 1)
                ->withMiddleware(new TimingMiddleware('inner'))
                ->through(fn($x) => $x * 2)
                ->process();
            
            $computation = $result->computation();
            $timings = $computation->all(TimingTag::class);
            
            // Should have timing tags from middleware
            expect(count($timings))->toBeGreaterThanOrEqual(2);
            
            // Find timings by operation name
            $outerTimings = array_filter($timings, fn($t) => $t->operationName === 'outer');
            $innerTimings = array_filter($timings, fn($t) => $t->operationName === 'inner');
            
            expect(count($outerTimings))->toBeGreaterThanOrEqual(1);
            expect(count($innerTimings))->toBeGreaterThanOrEqual(1);
        });
    });

    describe('Complex Middleware Scenarios', function () {
        it('handles middleware chain with conditional execution', function () {
            $conditionalMiddleware = new class implements PipelineMiddlewareInterface {
                public function handle(Computation $computation, callable $next): Computation {
                    $value = $computation->result()->unwrap();
                    
                    // Only execute next middleware/processor if value is numeric
                    if (is_numeric($value)) {
                        return $next($computation);
                    }
                    
                    return $computation; // Skip execution
                }
            };
            
            // Numeric value - should execute processor
            $result1 = Pipeline::for(10)
                ->withMiddleware($conditionalMiddleware)
                ->through(fn($x) => $x * 2)
                ->process();
            
            expect($result1->value())->toBe(20);
            
            // Non-numeric value - should skip processor
            $result2 = Pipeline::for('text')
                ->withMiddleware($conditionalMiddleware)
                ->through(fn($x) => $x * 2) // This would fail if executed
                ->process();
            
            expect($result2->value())->toBe('text');
        });

        it('handles middleware that modifies computation context', function () {
            $contextMiddleware = new class implements PipelineMiddlewareInterface {
                public function handle(Computation $computation, callable $next): Computation {
                    // Add user context to computation
                    $userTag = new IntegrationTraceTag('user-123');
                    $contextComputation = $computation->with($userTag);
                    
                    $result = $next($contextComputation);
                    
                    // Ensure context is preserved in result
                    if (!$result->has(IntegrationTraceTag::class)) {
                        $result = $result->with($userTag);
                    }
                    
                    return $result;
                }
            };
            
            $result = Pipeline::for('data')
                ->withMiddleware($contextMiddleware)
                ->through(fn($x) => strtoupper($x))
                ->process();
            
            $computation = $result->computation();
            expect($computation->has(IntegrationTraceTag::class))->toBeTrue();
            expect($computation->first(IntegrationTraceTag::class)->traceId)->toBe('user-123');
            expect($result->value())->toBe('DATA');
        });
    });
});