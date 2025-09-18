<?php declare(strict_types=1);

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\PendingExecution;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\TagMap\Contracts\TagInterface;

class ExecutionTestTag implements TagInterface {
    public function __construct(public readonly string $operation) {}
}

describe('PendingExecution Incremental Tests - Missing Coverage', function () {

    describe('constructor', function () {
        it('can execute pipeline after construction', function () {
            $state = ProcessingState::with(42);
            $pipeline = new Pipeline();
            $execution = new PendingExecution($state, $pipeline);
            $result = $execution->execute();
            expect($result->value())->toBe(42);
        });
    });

    describe('result method', function () {
        it('returns Result object from execution', function () {
            $pending = Pipeline::builder()
                ->through(fn($x) => $x * 2)
                ->create()
                ->executeWith(ProcessingState::with(42));
            
            $result = $pending->result();
            
            expect($result)->toBeInstanceOf(Success::class);
            expect($result->isSuccess())->toBeTrue();
            expect($result->unwrap())->toBe(84);
        });

        it('returns failed Result on pipeline error', function () {
            $pending = Pipeline::builder()
                ->through(fn($x) => throw new RuntimeException('Pipeline error'))
                ->create()
                ->executeWith(ProcessingState::with(42));
            
            $result = $pending->result();
            
            expect($result)->toBeInstanceOf(Failure::class);
            expect($result->isFailure())->toBeTrue();
            expect($result->exception()->getMessage())->toBe('Pipeline error');
        });

        it('caches result after first call', function () {
            $executionCount = 0;
            $pending = Pipeline::builder()
                ->through(function($x) use (&$executionCount) {
                    $executionCount++;
                    return $x * 2;
                })
                ->create()
                ->executeWith(ProcessingState::with(42));
            
            $result1 = $pending->result();
            $result2 = $pending->result();
            
            expect($executionCount)->toBe(1);
            expect($result1->unwrap())->toBe($result2->unwrap());
        });
    });

    describe('mixed execution methods', function () {
        it('maintains consistency across different access methods', function () {
            $pending = Pipeline::builder()
                ->through(fn($x) => $x * 3)
                ->through(fn($x) => $x + 5)
                ->create()
                ->executeWith(ProcessingState::with(10));
            
            $value = $pending->value();
            $state = $pending->state();
            $result = $pending->result();
            $isSuccess = $pending->isSuccess();
            
            expect($value)->toBe(35);
            expect($state->value())->toBe(35);
            expect($result->unwrap())->toBe(35);
            expect($isSuccess)->toBeTrue();
        });

        it('handles failure consistently across methods', function () {
            $pending = Pipeline::builder()
                ->through(fn($x) => $x * 2)
                ->through(fn($x) => throw new RuntimeException('Test error'))
                ->create()
                ->executeWith(ProcessingState::with(10));
            
            $isFailure = $pending->isFailure();
            $exception = $pending->exception();
            $state = $pending->state();
            $result = $pending->result();
            
            expect($isFailure)->toBeTrue();
            expect($exception)->toBeInstanceOf(RuntimeException::class);
            expect($exception->getMessage())->toBe('Test error');
            expect($state->isFailure())->toBeTrue();
            expect($result->isFailure())->toBeTrue();
        });
    });

    describe('for method with tags', function () {
        it('updates initial state with tags', function () {
            $tag = new ExecutionTestTag('updated');
            $pending = Pipeline::builder()->create()->executeWith(ProcessingState::with('original'));
            
            $newExecution = $pending->for('updated', [$tag]);
            $state = $newExecution->state();
            
            expect($state->value())->toBe('updated');
            expect($state->hasTag(ExecutionTestTag::class))->toBeTrue();
            expect($state->tags()->only(ExecutionTestTag::class)->first()->operation)->toBe('updated');
        });

        it('resets cached output when for is called', function () {
            $executionCount = 0;
            $pending = Pipeline::builder()
                ->through(function($x) use (&$executionCount) {
                    $executionCount++;
                    return $x * 2;
                })
                ->create()
                ->executeWith(ProcessingState::with(10));
            
            // First execution
            $value1 = $pending->value();
            expect($executionCount)->toBe(1);
            
            // Change input - should trigger re-execution
            $value2 = $pending->for(20)->value();
            expect($executionCount)->toBe(2);
            expect($value1)->toBe(20);
            expect($value2)->toBe(40);
        });
    });

    describe('integration with complex pipelines', function () {
        it('works with pipelines containing middleware', function () {
            $middleware = new class implements CanProcessState {
                public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
                    return $next ? $next($state->addTags(new ExecutionTestTag('middleware'))) : $state;
                }
            };
            
            $pending = Pipeline::builder()
                ->withOperator($middleware)
                ->through(fn($x) => $x * 2)
                ->create()
                ->executeWith(ProcessingState::with(10));
            
            $state = $pending->state();
            
            expect($state->value())->toBe(20);
            expect($state->hasTag(ExecutionTestTag::class))->toBeTrue();
        });

        it('works with pipelines containing hooks', function () {
            $hookExecuted = false;
            
            $pending = Pipeline::builder()
                ->beforeEach(function($state) use (&$hookExecuted) {
                    $hookExecuted = true;
                    return $state;
                })
                ->through(fn($x) => $x * 2)
                ->create()
                ->executeWith(ProcessingState::with(10));
            
            $value = $pending->value();
            
            expect($value)->toBe(20);
            expect($hookExecuted)->toBeTrue();
        });

        it('works with pipelines containing finalizers', function () {
            $pending = Pipeline::builder()
                ->through(fn($x) => $x * 2)
                ->finally(fn($state) => $state->value() . '_finalized')
                ->create()
                ->executeWith(ProcessingState::with(10));
            
            $value = $pending->value();
            
            expect($value)->toBe('20_finalized');
        });
    });

    describe('edge cases', function () {
        it('handles null values correctly', function () {
            $pending = Pipeline::builder()
                ->through(fn($x) => $x ?? 'was_null')
                ->create()
                ->executeWith(ProcessingState::with(null));
            
            $value = $pending->value();
            
            expect($value)->toBe('was_null');
        });

        it('handles empty arrays in stream', function () {
            $pending = Pipeline::builder()
                ->create()
                ->executeWith(ProcessingState::with([]));
            
            $stream = $pending->stream();
            $results = iterator_to_array($stream);
            
            expect($results)->toBeEmpty();
        });

        it('preserves exception details through execution', function () {
            $originalException = new RuntimeException('Original error', 123);
            
            $pending = Pipeline::builder()
                ->through(fn($x) => throw $originalException)
                ->create()
                ->executeWith(ProcessingState::with(10));
            
            $exception = $pending->exception();
            
            expect($exception)->toBe($originalException);
            expect($exception->getCode())->toBe(123);
        });
    });
});