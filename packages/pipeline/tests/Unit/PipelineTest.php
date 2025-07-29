<?php declare(strict_types=1);

use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PendingPipelineExecution;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Pipeline\StampInterface;
use Cognesy\Utils\Result\Result;

// Test implementations for unit testing
class UnitTestStamp implements StampInterface
{
    public function __construct(public readonly string $label) {}
}

class MockMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(public readonly string $id) {}

    public function handle(Envelope $envelope, callable $next): Envelope
    {
        return $next($envelope->with(new UnitTestStamp($this->id)));
    }
}

describe('Pipeline Unit Tests', function () {
    describe('Factory Methods', function () {
        it('creates pipeline with make()', function () {
            $pipeline = Pipeline::make();
            expect($pipeline)->toBeInstanceOf(Pipeline::class);
        });

        it('creates pipeline with from() using callable source', function () {
            $pipeline = Pipeline::from(fn() => 'test value');
            $result = $pipeline->process()->payload();
            expect($result)->toBe('test value');
        });

        it('creates pipeline with for() using direct value', function () {
            $pipeline = Pipeline::for(42);
            $result = $pipeline->process()->payload();
            expect($result)->toBe(42);
        });
    });

    describe('Processor Management', function () {
        it('adds processors with through()', function () {
            $pipeline = Pipeline::for(5)
                ->through(fn($x) => $x * 2)
                ->through(fn($x) => $x + 1);

            $result = $pipeline->process()->payload();
            expect($result)->toBe(11); // (5 * 2) + 1
        });

        it('handles conditional processors with when()', function () {
            $pipeline = Pipeline::for(10)
                ->when(fn($env) => $env->result()->unwrap() > 5, fn($x) => $x * 3)
                ->when(fn($env) => $env->result()->unwrap() > 50, fn($x) => $x + 100);

            $result = $pipeline->process()->payload();
            expect($result)->toBe(10 * 3); // 30, second condition not met
        });

        it('handles side effects with tap()', function () {
            $sideEffect = null;
            
            $pipeline = Pipeline::for('test')
                ->tap(function($x) use (&$sideEffect) {
                    $sideEffect = strtoupper($x);
                })
                ->through(fn($x) => $x . '!');

            $result = $pipeline->process()->payload();
            
            expect($result)->toBe('test!');
            expect($sideEffect)->toBe('TEST');
        });

        it('sets finalizer with then()', function () {
            $pipeline = Pipeline::for(10)
                ->through(fn($x) => $x * 2)
                ->then(fn($result) => 'Final: ' . $result->unwrap());

            $result = $pipeline->process()->payload();
            expect($result)->toBe('Final: 20');
        });
    });

    describe('Middleware Management', function () {
        it('adds middleware with withMiddleware()', function () {
            $pipeline = Pipeline::for('test')
                ->withMiddleware(new MockMiddleware('first'))
                ->through(fn($x) => strtoupper($x));

            $envelope = $pipeline->process()->envelope();
            
            expect($envelope->result()->unwrap())->toBe('TEST');
            expect($envelope->has(UnitTestStamp::class))->toBeTrue();
            expect($envelope->first(UnitTestStamp::class)->label)->toBe('first');
        });

        it('prepends middleware with prependMiddleware()', function () {
            $pipeline = Pipeline::for('test')
                ->withMiddleware(new MockMiddleware('second'))
                ->prependMiddleware(new MockMiddleware('first'))
                ->through(fn($x) => $x);

            $envelope = $pipeline->process()->envelope();
            $stamps = $envelope->all(UnitTestStamp::class);
            
            expect($stamps[0]->label)->toBe('first');
            expect($stamps[1]->label)->toBe('second');
        });
    });

    describe('Hook System', function () {
        it('adds stamps with withStamp()', function () {
            $stamp1 = new UnitTestStamp('stamp1');
            $stamp2 = new UnitTestStamp('stamp2');
            
            $pipeline = Pipeline::for('test')
                ->withStamp($stamp1, $stamp2)
                ->through(fn($x) => $x);

            $envelope = $pipeline->process()->envelope();
            
            expect($envelope->count(UnitTestStamp::class))->toBe(2);
            expect($envelope->first(UnitTestStamp::class)->label)->toBe('stamp1');
            expect($envelope->last(UnitTestStamp::class)->label)->toBe('stamp2');
        });

        it('executes beforeEach hooks', function () {
            $executions = [];
            
            $pipeline = Pipeline::for(5)
                ->beforeEach(function($env) use (&$executions) {
                    $executions[] = 'before:' . $env->result()->unwrap();
                    return $env;
                })
                ->through(fn($x) => $x * 2);

            $pipeline->process()->payload(); // Need to execute the pending result
            
            expect($executions)->toBe(['before:5']);
        });

        it('executes afterEach hooks', function () {
            $executions = [];
            
            $pipeline = Pipeline::for(5)
                ->through(fn($x) => $x * 2)
                ->afterEach(function($env) use (&$executions) {
                    $executions[] = 'after:' . $env->result()->unwrap();
                    return $env;
                });

            $pipeline->process()->payload(); // Need to execute the pending result
            
            expect($executions)->toBe(['after:10']);
        });

        it('handles early termination with finishWhen()', function () {
            $executions = [];
            
            $pipeline = Pipeline::for(1)
                ->through(function($x) use (&$executions) {
                    $executions[] = 'step1';
                    return $x + 1;
                })
                ->finishWhen(fn($env) => $env->result()->unwrap() >= 2)
                ->through(function($x) use (&$executions) {
                    $executions[] = 'step2';
                    return $x + 10;
                });

            $result = $pipeline->process()->payload();
            
            expect($result)->toBe(2);
            expect($executions)->toBe(['step1']); // step2 not executed
        });

        it('handles failures with onFailure()', function () {
            $failureHandled = false;
            
            $pipeline = Pipeline::for('test')
                ->through(fn($x) => throw new Exception('Test error'))
                ->onFailure(function($env) use (&$failureHandled) {
                    $failureHandled = true;
                    return $env;
                });

            $result = $pipeline->process();
            
            expect($result->success())->toBeFalse();
            expect($failureHandled)->toBeTrue();
        });
    });

    describe('Execution', function () {
        it('returns PendingPipelineExecution from process()', function () {
            $pipeline = Pipeline::for('test');
            $pending = $pipeline->process();
            
            expect($pending)->toBeInstanceOf(PendingPipelineExecution::class);
        });

        it('processes with initial value override', function () {
            $pipeline = Pipeline::for('original')
                ->through(fn($x) => strtoupper($x));

            $result = $pipeline->process('override')->payload();
            
            expect($result)->toBe('OVERRIDE');
        });

        it('processes with initial stamps', function () {
            $stamp = new UnitTestStamp('initial');
            
            $pipeline = Pipeline::for('test')
                ->through(fn($x) => $x);

            $envelope = $pipeline->process(stamps: [$stamp])->envelope();
            
            expect($envelope->has(UnitTestStamp::class))->toBeTrue();
            expect($envelope->first(UnitTestStamp::class)->label)->toBe('initial');
        });

        it('processes streams with stream()', function () {
            $pipeline = Pipeline::for(null)
                ->through(fn($x) => $x * 2);

            $results = [];
            foreach ($pipeline->stream([1, 2, 3]) as $pending) {
                $results[] = $pending->payload();
            }
            
            expect($results)->toBe([2, 4, 6]);
        });
    });

    describe('Error Handling', function () {
        it('handles processor exceptions', function () {
            $pipeline = Pipeline::for('test')
                ->through(fn($x) => throw new Exception('Processing error'));

            $result = $pipeline->process();
            
            expect($result->success())->toBeFalse();
            expect($result->failure())->toBeInstanceOf(Exception::class);
        });

        it('handles null values based on NullStrategy', function () {
            $pipelineAllow = Pipeline::for('test')
                ->through(fn($x) => null, NullStrategy::Allow);

            $pipelineFail = Pipeline::for('test')
                ->through(fn($x) => null, NullStrategy::Fail);

            expect($pipelineAllow->process()->success())->toBeTrue();
            expect($pipelineAllow->process()->payload())->toBeNull();
            
            expect($pipelineFail->process()->success())->toBeFalse();
        });

        it('short-circuits on failures', function () {
            $executed = [];
            
            $pipeline = Pipeline::for('test')
                ->through(function($x) use (&$executed) {
                    $executed[] = 'step1';
                    throw new Exception('Error');
                })
                ->through(function($x) use (&$executed) {
                    $executed[] = 'step2';
                    return $x;
                });

            $pipeline->process()->payload(); // Need to execute the pending result
            
            expect($executed)->toBe(['step1']); // step2 not executed
        });
    });

    describe('Envelope Processing', function () {
        it('handles envelope-aware processors', function () {
            $pipeline = Pipeline::for('test')
                ->through(function(Envelope $env) {
                    return $env
                        ->with(new UnitTestStamp('processed'))
                        ->withResult(Result::success(strtoupper($env->result()->unwrap())));
                });

            $envelope = $pipeline->process()->envelope();
            
            expect($envelope->result()->unwrap())->toBe('TEST');
            expect($envelope->has(UnitTestStamp::class))->toBeTrue();
        });

        it('differentiates between envelope and value processors', function () {
            $pipeline = Pipeline::for('hello')
                ->through(fn($x) => $x . ' world') // Value processor
                ->through(function(Envelope $env) { // Envelope processor
                    return $env->with(new UnitTestStamp('envelope'));
                });

            $envelope = $pipeline->process()->envelope();
            
            expect($envelope->result()->unwrap())->toBe('hello world');
            expect($envelope->has(UnitTestStamp::class))->toBeTrue();
        });
    });
});