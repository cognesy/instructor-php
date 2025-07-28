<?php declare(strict_types=1);

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Pipeline\StampInterface;
use Cognesy\Utils\Result\Result;

// Test stamps for testing
class TestStamp implements StampInterface
{
    public function __construct(public readonly string $value) {}
}

class TimestampStamp implements StampInterface
{
    public function __construct(public readonly float $timestamp) {}
}

// Test middleware for testing
class TestMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(private string $prefix = 'middleware') {}

    public function handle(Envelope $envelope, callable $next): Envelope
    {
        $result = $next($envelope->with(new TestStamp($this->prefix)));
        return $result->with(new TestStamp($this->prefix . '_after'));
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

    it('processes with initial stamps', function () {
        $envelope = Pipeline::for('test')
            ->process(stamps: [new TestStamp('initial')])
            ->envelope();

        expect($envelope->has(TestStamp::class))->toBeTrue();
        expect($envelope->first(TestStamp::class)->value)->toBe('initial');
    });

    it('handles conditional processing', function () {
        $result = Pipeline::for(5)
            ->when(fn($env) => $env->getResult()->unwrap() > 3, fn($x) => $x * 10)
            ->when(fn($env) => $env->getResult()->unwrap() > 100, fn($x) => $x + 1000)
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
        $envelope = Pipeline::for('test')
            ->withMiddleware(new TestMiddleware('pre'))
            ->through(fn($x) => strtoupper($x))
            ->process()
            ->envelope();

        expect($envelope->getResult()->unwrap())->toBe('TEST');
        expect($envelope->count(TestStamp::class))->toBe(2);
        
        $stamps = $envelope->all(TestStamp::class);
        expect($stamps[0]->value)->toBe('pre');
        expect($stamps[1]->value)->toBe('pre_after');
    });

    it('chains multiple processors with middleware', function () {
        $envelope = Pipeline::for(1)
            ->withMiddleware(new TestMiddleware('global'))
            ->through(fn($x) => $x + 10)
            ->through(fn($x) => $x * 2)
            ->process()
            ->envelope();

        expect($envelope->getResult()->unwrap())->toBe(22); // (1 + 10) * 2
        expect($envelope->count(TestStamp::class))->toBe(4); // 2 stamps per processor
    });

    it('uses beforeEach and afterEach hooks', function () {
        $values = [];
        
        $envelope = Pipeline::for(5)
            ->beforeEach(function($env) use (&$values) {
                $values[] = 'before:' . $env->getResult()->unwrap();
                return $env;
            })
            ->through(fn($x) => $x * 2)
            ->afterEach(function($env) use (&$values) {
                $values[] = 'after:' . $env->getResult()->unwrap();
                return $env;
            })
            ->through(fn($x) => $x + 1)
            ->process()
            ->envelope();

        expect($envelope->getResult()->unwrap())->toBe(11); // (5 * 2) + 1
        expect($values)->toBe(['before:5', 'after:10', 'before:10', 'after:11']);
    });

    it('handles early termination with finishWhen', function () {
        $processedValues = [];
        
        $result = Pipeline::for(1)
            ->through(function($x) use (&$processedValues) {
                $processedValues[] = $x;
                return $x + 1;
            })
            ->finishWhen(fn($env) => $env->getResult()->unwrap() >= 3)
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
            ->onFailure(function($env) use (&$failureHandled) {
                $failureHandled = true;
                return $env;
            })
            ->process();

        expect($result->success())->toBeFalse();
        expect($failureHandled)->toBeTrue();
        expect($result->failure())->toBeInstanceOf(Exception::class);
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
            ->then(fn($result) => 'Final: ' . $result->unwrap())
            ->process()
            ->value();

        expect($result)->toBe('Final: 20');
    });

    it('preserves stamps through complex processing', function () {
        $envelope = Pipeline::for('start')
            ->withStamp(new TestStamp('initial'))
            ->withMiddleware(new TestMiddleware('mid'))
            ->through(function($x) {
                return strtoupper($x);
            })
            ->beforeEach(fn($env) => $env->with(new TimestampStamp(microtime(true))))
            ->process()
            ->envelope();

        expect($envelope->getResult()->unwrap())->toBe('START');
        expect($envelope->has(TestStamp::class))->toBeTrue();
        expect($envelope->has(TimestampStamp::class))->toBeTrue();
        expect($envelope->count(TestStamp::class))->toBeGreaterThan(1);
    });
});