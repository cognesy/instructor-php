<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Middleware\PipelineMiddlewareInterface;
use Cognesy\Pipeline\PendingExecution;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Tag\TagInterface;
use Cognesy\Utils\Result\Result;

// Test implementations for unit testing
class UnitTestTag implements TagInterface
{
    public function __construct(public readonly string $label) {}
}

class MockMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(public readonly string $id) {}

    public function handle(Computation $computation, callable $next): Computation
    {
        return $next($computation->with(new UnitTestTag($this->id)));
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
            $result = $pipeline->process()->value();
            expect($result)->toBe('test value');
        });

        it('creates pipeline with for() using direct value', function () {
            $pipeline = Pipeline::for(42);
            $result = $pipeline->process()->value();
            expect($result)->toBe(42);
        });
    });

    describe('Processor Management', function () {
        it('adds processors with through()', function () {
            $pipeline = Pipeline::for(5)
                ->through(fn($x) => $x * 2)
                ->through(fn($x) => $x + 1);

            $result = $pipeline->process()->value();
            expect($result)->toBe(11); // (5 * 2) + 1
        });

        it('handles conditional processors with when()', function () {
            $pipeline = Pipeline::for(10)
                ->when(fn($computation) => $computation->result()->unwrap() > 5, fn($x) => $x * 3)
                ->when(fn($computation) => $computation->result()->unwrap() > 50, fn($x) => $x + 100);

            $result = $pipeline->process()->value();
            expect($result)->toBe(10 * 3); // 30, second condition not met
        });

        it('handles side effects with tap()', function () {
            $sideEffect = null;
            
            $pipeline = Pipeline::for('test')
                ->tap(function($x) use (&$sideEffect) {
                    $sideEffect = strtoupper($x);
                })
                ->through(fn($x) => $x . '!');

            $result = $pipeline->process()->value();
            
            expect($result)->toBe('test!');
            expect($sideEffect)->toBe('TEST');
        });

        it('sets finalizer with then()', function () {
            $pipeline = Pipeline::for(10)
                ->through(fn($x) => $x * 2)
                ->finally(fn($result) => 'Final: ' . $result->unwrap());

            $result = $pipeline->process()->value();
            expect($result)->toBe('Final: 20');
        });
    });

    describe('Middleware Management', function () {
        it('adds middleware with withMiddleware()', function () {
            $pipeline = Pipeline::for('test')
                ->withMiddleware(new MockMiddleware('first'))
                ->through(fn($x) => strtoupper($x));

            $computation = $pipeline->process()->computation();
            
            expect($computation->result()->unwrap())->toBe('TEST');
            expect($computation->has(UnitTestTag::class))->toBeTrue();
            expect($computation->first(UnitTestTag::class)->label)->toBe('first');
        });

        it('prepends middleware with prependMiddleware()', function () {
            $pipeline = Pipeline::for('test')
                ->withMiddleware(new MockMiddleware('second'))
                ->prependMiddleware(new MockMiddleware('first'))
                ->through(fn($x) => $x);

            $computation = $pipeline->process()->computation();
            $tags = $computation->all(UnitTestTag::class);
            
            expect($tags[0]->label)->toBe('first');
            expect($tags[1]->label)->toBe('second');
        });
    });

    describe('Hook System', function () {
        it('adds tags with withTag()', function () {
            $tag1 = new UnitTestTag('tag1');
            $tag2 = new UnitTestTag('tag2');
            
            $pipeline = Pipeline::for('test')
                ->withTag($tag1, $tag2)
                ->through(fn($x) => $x);

            $computation = $pipeline->process()->computation();
            
            expect($computation->count(UnitTestTag::class))->toBe(2);
            expect($computation->first(UnitTestTag::class)->label)->toBe('tag1');
            expect($computation->last(UnitTestTag::class)->label)->toBe('tag2');
        });

        it('executes beforeEach hooks', function () {
            $executions = [];
            
            $pipeline = Pipeline::for(5)
                ->beforeEach(function($computation) use (&$executions) {
                    $executions[] = 'before:' . $computation->result()->unwrap();
                    return $computation;
                })
                ->through(fn($x) => $x * 2);

            $pipeline->process()->value(); // Need to execute the pending result
            
            expect($executions)->toBe(['before:5']);
        });

        it('executes afterEach hooks', function () {
            $executions = [];
            
            $pipeline = Pipeline::for(5)
                ->through(fn($x) => $x * 2)
                ->afterEach(function($computation) use (&$executions) {
                    $executions[] = 'after:' . $computation->result()->unwrap();
                    return $computation;
                });

            $pipeline->process()->value(); // Need to execute the pending result
            
            expect($executions)->toBe(['after:10']);
        });

        it('handles early termination with finishWhen()', function () {
            $executions = [];
            
            $pipeline = Pipeline::for(1)
                ->through(function($x) use (&$executions) {
                    $executions[] = 'step1';
                    return $x + 1;
                })
                ->finishWhen(fn($computation) => $computation->result()->unwrap() >= 2)
                ->through(function($x) use (&$executions) {
                    $executions[] = 'step2';
                    return $x + 10;
                });

            $result = $pipeline->process()->value();
            
            expect($result)->toBe(2);
            expect($executions)->toBe(['step1']); // step2 not executed
        });

        it('handles failures with onFailure()', function () {
            $failureHandled = false;
            
            $pipeline = Pipeline::for('test')
                ->through(fn($x) => throw new Exception('Test error'))
                ->onFailure(function($computation) use (&$failureHandled) {
                    $failureHandled = true;
                    return $computation;
                });

            $result = $pipeline->process();
            
            expect($result->isSuccess())->toBeFalse();
            expect($failureHandled)->toBeTrue();
        });
    });

    describe('Execution', function () {
        it('returns PendingPipelineExecution from process()', function () {
            $pipeline = Pipeline::for('test');
            $pending = $pipeline->process();
            
            expect($pending)->toBeInstanceOf(PendingExecution::class);
        });

        it('processes with initial value override', function () {
            $pipeline = Pipeline::for('original')
                ->through(fn($x) => strtoupper($x));

            $result = $pipeline->process('override')->value();
            
            expect($result)->toBe('OVERRIDE');
        });

        it('processes with initial tags', function () {
            $tag = new UnitTestTag('initial');
            
            $pipeline = Pipeline::for('test')
                ->through(fn($x) => $x);

            $computation = $pipeline->process(tags: [$tag])->computation();
            
            expect($computation->has(UnitTestTag::class))->toBeTrue();
            expect($computation->first(UnitTestTag::class)->label)->toBe('initial');
        });

        it('processes streams with stream()', function () {
            $pipeline = Pipeline::for(null)
                ->through(fn($x) => $x * 2);

            $results = [];
            foreach ($pipeline->stream([1, 2, 3]) as $pending) {
                $results[] = $pending->value();
            }
            
            expect($results)->toBe([2, 4, 6]);
        });
    });

    describe('Error Handling', function () {
        it('handles processor exceptions', function () {
            $pipeline = Pipeline::for('test')
                ->through(fn($x) => throw new Exception('Processing error'));

            $result = $pipeline->process();
            
            expect($result->isSuccess())->toBeFalse();
            expect($result->exception())->toBeInstanceOf(Exception::class);
        });

        it('handles null values based on NullStrategy', function () {
            $pipelineAllow = Pipeline::for('test')
                ->through(fn($x) => null, NullStrategy::Allow);

            $pipelineFail = Pipeline::for('test')
                ->through(fn($x) => null, NullStrategy::Fail);

            expect($pipelineAllow->process()->isSuccess())->toBeTrue();
            expect($pipelineAllow->process()->value())->toBeNull();
            
            expect($pipelineFail->process()->isSuccess())->toBeFalse();
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

            $pipeline->process()->value(); // Need to execute the pending result
            
            expect($executed)->toBe(['step1']); // step2 not executed
        });
    });

    describe('Computation Processing', function () {
        it('handles computation-aware processors', function () {
            $pipeline = Pipeline::for('test')
                ->through(function(Computation $computation) {
                    return $computation
                        ->with(new UnitTestTag('processed'))
                        ->withResult(Result::success(strtoupper($computation->result()->unwrap())));
                });

            $computation = $pipeline->process()->computation();
            
            expect($computation->result()->unwrap())->toBe('TEST');
            expect($computation->has(UnitTestTag::class))->toBeTrue();
        });

        it('differentiates between computation and value processors', function () {
            $pipeline = Pipeline::for('hello')
                ->through(fn($x) => $x . ' world') // Value processor
                ->through(function(Computation $computation) { // Computation processor
                    return $computation->with(new UnitTestTag('computation'));
                });

            $computation = $pipeline->process()->computation();
            
            expect($computation->result()->unwrap())->toBe('hello world');
            expect($computation->has(UnitTestTag::class))->toBeTrue();
        });
    });
});