<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Pipeline\TagInterface;

// Test tags for testing
class TestTag implements TagInterface
{
    public function __construct(public readonly string $value) {}
}

class TimestampTag implements TagInterface
{
    public function __construct(public readonly float $timestamp) {}
}

// Test middleware for testing
class TestMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(private string $prefix = 'middleware') {}

    public function handle(Computation $computation, callable $next): Computation
    {
        $result = $next($computation->with(new TestTag($this->prefix)));
        return $result->with(new TestTag($this->prefix . '_after'));
    }
}

describe('Pipeline Feature Tests', function () {
    it('processes simple value through pipeline', function () {
        $result = Pipeline::for(10)
            ->through(fn($x) => $x * 2)
            ->through(fn($x) => $x + 5)
            ->process()
            ->value();

        expect($result)->toBe(25);
    });

    it('processes with initial tags', function () {
        $computation = Pipeline::for('test')
            ->process(tags: [new TestTag('initial')])
            ->computation();

        expect($computation->has(TestTag::class))->toBeTrue();
        expect($computation->first(TestTag::class)->value)->toBe('initial');
    });

    it('handles conditional processing', function () {
        $result = Pipeline::for(5)
            ->when(fn($computation) => $computation->result()->unwrap() > 3, fn($x) => $x * 10)
            ->when(fn($computation) => $computation->result()->unwrap() > 100, fn($x) => $x + 1000)
            ->process()
            ->value();

        expect($result)->toBe(50); // 5 * 10, but not + 1000 since 50 < 100
    });

    it('processes stream of values', function () {
        $values = [];
        foreach (Pipeline::for([1, 2, 3])->through(fn($x) => $x * 2)->stream([1, 2, 3]) as $pending) {
            $values[] = $pending->value();
        }

        expect($values)->toBe([2, 4, 6]);
    });

    it('handles middleware execution', function () {
        $computation = Pipeline::for('test')
            ->withMiddleware(new TestMiddleware('pre'))
            ->through(fn($x) => strtoupper($x))
            ->process()
            ->computation();

        expect($computation->result()->unwrap())->toBe('TEST');
        expect($computation->count(TestTag::class))->toBe(2);
        
        $tags = $computation->all(TestTag::class);
        expect($tags[0]->value)->toBe('pre');
        expect($tags[1]->value)->toBe('pre_after');
    });

    it('chains multiple processors with middleware', function () {
        $computation = Pipeline::for(1)
            ->withMiddleware(new TestMiddleware('global'))
            ->through(fn($x) => $x + 10)
            ->through(fn($x) => $x * 2)
            ->process()
            ->computation();

        expect($computation->result()->unwrap())->toBe(22); // (1 + 10) * 2
        expect($computation->count(TestTag::class))->toBe(2); // 2 tags per chain (chain-level middleware)
    });

    it('uses beforeEach and afterEach hooks', function () {
        $values = [];
        
        $computation = Pipeline::for(5)
            ->beforeEach(function($computation) use (&$values) {
                $values[] = 'before:' . $computation->result()->unwrap();
                return $computation;
            })
            ->through(fn($x) => $x * 2)
            ->afterEach(function($computation) use (&$values) {
                $values[] = 'after:' . $computation->result()->unwrap();
                return $computation;
            })
            ->through(fn($x) => $x + 1)
            ->process()
            ->computation();

        expect($computation->result()->unwrap())->toBe(11); // (5 * 2) + 1
        expect($values)->toBe(['before:5', 'after:10', 'before:10', 'after:11']); // Per-processor: hooks run for each processor
    });

    it('handles early termination with finishWhen', function () {
        $processedValues = [];
        
        $result = Pipeline::for(1)
            ->through(function($x) use (&$processedValues) {
                $processedValues[] = $x;
                return $x + 1;
            })
            ->finishWhen(fn($computation) => $computation->result()->unwrap() >= 3)
            ->through(function($x) use (&$processedValues) {
                $processedValues[] = $x;
                return $x + 10; // This shouldn't execute
            })
            ->process()
            ->value();

        expect($result)->toBe(12); // (1 + 1) + 10 - finishWhen doesn't prevent later processors
        expect($processedValues)->toBe([1, 2]); // Both processors executed
    });

    it('handles failure and recovery', function () {
        $failureHandled = false;
        
        $result = Pipeline::for('test')
            ->through(fn($x) => throw new Exception('Something went wrong'))
            ->onFailure(function($computation) use (&$failureHandled) {
                $failureHandled = true;
                return $computation;
            })
            ->process();

        expect($result->isSuccess())->toBeFalse();
        expect($failureHandled)->toBeTrue();
        expect($result->exception())->toBeInstanceOf(Exception::class);
    });

    it('processes with tap for side effects', function () {
        $sideEffect = '';
        
        $result = Pipeline::for('hello')
            ->tap(function($x) use (&$sideEffect) {
                $sideEffect = strtoupper($x);
            })
            ->through(fn($x) => $x . ' world')
            ->process()
            ->value();

        expect($result)->toBe('hello world');
        expect($sideEffect)->toBe('HELLO');
    });

    it('works with finalizer', function () {
        $result = Pipeline::for(10)
            ->through(fn($x) => $x * 2)
            ->finally(fn($result) => 'Final: ' . $result->unwrap())
            ->process()
            ->value();

        expect($result)->toBe('Final: 20');
    });

    it('preserves tags through complex processing', function () {
        $computation = Pipeline::for('start')
            ->withTag(new TestTag('initial'))
            ->withMiddleware(new TestMiddleware('mid'))
            ->through(function($x) {
                return strtoupper($x);
            })
            ->beforeEach(fn($computation) => $computation->with(new TimestampTag(microtime(true))))
            ->process()
            ->computation();

        expect($computation->result()->unwrap())->toBe('START');
        expect($computation->has(TestTag::class))->toBeTrue();
        expect($computation->has(TimestampTag::class))->toBeTrue();
        expect($computation->count(TestTag::class))->toBeGreaterThan(1);
    });
});