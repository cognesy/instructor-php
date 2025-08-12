<?php declare(strict_types=1);

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\PipelineBuilder;
use Cognesy\Pipeline\ProcessingState;

class BuilderTestTag implements TagInterface {
    public function __construct(public readonly string $name) {}
}

class TestMiddleware implements CanProcessState {
    public function __construct(private string $name) {}
    
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $output = $state->addTags(new BuilderTestTag($this->name));
        return $next ? $next($output) : $output;
    }
}

class TestFinalizer implements CanProcessState {
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $output = $state->value() . '_finalized';
        $newState = ProcessingState::with($output);
        return $next ? $next($newState) : $newState;
    }
}

describe('PipelineBuilder Incremental Tests - Missing Coverage', function () {

    describe('middleware configuration', function () {
        describe('prependMiddleware', function () {
            it('adds middleware at beginning of stack', function () {
                $middleware1 = new TestMiddleware('first');
                $middleware2 = new TestMiddleware('second');
                
                $result = (new PipelineBuilder())
                    ->withOperator($middleware1)
                    ->prependOperator($middleware2)
                    ->create()
                    ->executeWith(ProcessingState::with('test'))
                    ->state();
                
                $tags = $result->allTags(BuilderTestTag::class);
                expect($tags[0]->name)->toBe('second'); // Prepended executes first
                expect($tags[1]->name)->toBe('first');
            });
        });
    });

    describe('hook methods', function () {
        describe('beforeEach', function () {
            it('executes hook before each processor', function () {
                $executions = [];
                
                $result = (new PipelineBuilder())
                    ->beforeEach(function($state) use (&$executions) {
                        $executions[] = 'before_' . $state->value();
                        return $state;
                    })
                    ->through(fn($x) => $x * 2)
                    ->through(fn($x) => $x + 1)
                    ->create()
                    ->executeWith(ProcessingState::with(1))
                    ->value();
                
                expect($result)->toBe(3); // (1 * 2) + 1
                expect($executions)->toBe(['before_1', 'before_2']);
            });
        });

        describe('afterEach', function () {
            it('executes hook after each processor', function () {
                $executions = [];
                
                $result = (new PipelineBuilder())
                    ->afterEach(function($state) use (&$executions) {
                        $executions[] = 'after_' . $state->value();
                        return $state;
                    })
                    ->through(fn($x) => $x * 2)
                    ->through(fn($x) => $x + 1)
                    ->create()
                    ->executeWith(ProcessingState::with(1))
                    ->value();
                
                expect($result)->toBe(3); // (1 * 2) + 1
                expect($executions)->toBe(['after_2', 'after_3']);
            });
        });

        describe('finishWhen', function () {
            it('stops processing when condition is met', function () {
                $processedSteps = [];
                
                $result = (new PipelineBuilder())
                    ->finishWhen(fn($state) => $state->value() >= 3)
                    ->through(function($x) use (&$processedSteps) {
                        $processedSteps[] = 'step1';
                        return $x * 3;
                    })
                    ->through(function($x) use (&$processedSteps) {
                        $processedSteps[] = 'step2';
                        return $x + 10;
                    })
                    ->create()
                    ->executeWith(ProcessingState::with(1))
                    ->value();
                
                expect($result)->toBe(3); // Stops before second step
                expect($processedSteps)->toBe(['step1']);
            });
        });

        describe('onFailure', function () {
            it('executes handler on pipeline failure', function () {
                $failureHandled = false;
                $failureMessage = '';
                
                $result = (new PipelineBuilder())
                    ->onFailure(function(ProcessingState $state) use (&$failureHandled, &$failureMessage) {
                        $failureHandled = true;
                        $failureMessage = $state->exception()->getMessage();
                    })
                    ->through(fn($x) => throw new RuntimeException('Test error'))
                    ->create()
                    ->executeWith(ProcessingState::with(1));
                
                expect($result->isFailure())->toBeTrue();
                expect($failureHandled)->toBeTrue();
                expect($failureMessage)->toBe('Test error');
            });
        });

        describe('failWhen', function () {
            it('fails pipeline when condition is met', function () {
                $result = (new PipelineBuilder())
                    ->failWhen(fn($state) => $state->value() > 5, 'Value too large')
                    ->through(fn($x) => $x * 2)
                    ->create()
                    ->executeWith(ProcessingState::with(10));
                
                expect($result->isFailure())->toBeTrue();
                expect($result->exception()->getMessage())->toBe('Value too large');
            });

            it('continues when condition is not met', function () {
                $result = (new PipelineBuilder())
                    ->failWhen(fn($state) => $state->value() > 5, 'Value too large')
                    ->through(fn($x) => $x * 2)
                    ->create()
                    ->executeWith(ProcessingState::with(2))
                    ->value();
                
                expect($result)->toBe(4);
            });
        });
    });

    describe('processing methods', function () {
        describe('throughAll', function () {
            it('applies all callables in sequence', function () {
                $callables = [
                    fn($x) => $x * 2,
                    fn($x) => $x + 5,
                    fn($x) => $x / 2
                ];
                
                $result = (new PipelineBuilder())
                    ->throughAll(...$callables)
                    ->create()
                    ->executeWith(ProcessingState::with(10))
                    ->value();
                
                expect($result)->toBe(12.5); // ((10 * 2) + 5) / 2
            });
        });

        describe('throughProcessor', function () {
            it('adds processor that implements CanProcessState', function () {
                $processor = new class implements CanProcessState {
                    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
                        $output = $state->transform()->map(fn($x) => $x . '_processed')->state();
                        return $next ? $next($output) : $output;
                    }
                };
                
                $result = (new PipelineBuilder())
                    ->throughOperator($processor)
                    ->create()
                    ->executeWith(ProcessingState::with('test'))
                    ->value();
                
                expect($result)->toBe('test_processed');
            });
        });

        describe('tapWithState', function () {
            it('executes callback with ProcessingState', function () {
                $capturedState = null;
                
                $result = (new PipelineBuilder())
                    ->tapWithState(function($state) use (&$capturedState) {
                        $capturedState = $state;
                    })
                    ->create()
                    ->executeWith(ProcessingState::with('test', [new BuilderTestTag('tap_test')]))
                    ->value();
                
                expect($result)->toBe('test');
                expect($capturedState)->toBeInstanceOf(ProcessingState::class);
                expect($capturedState->hasTag(BuilderTestTag::class))->toBeTrue();
            });
        });

        describe('filterWithState', function () {
            it('filters based on ProcessingState condition', function () {
                $result = (new PipelineBuilder())
                    ->filterWithState(fn($state) => $state->hasTag(BuilderTestTag::class))
                    ->through(fn($x) => $x . '_passed')
                    ->create()
                    ->executeWith(ProcessingState::with('test', [new BuilderTestTag('filter_test')]))
                    ->value();
                
                expect($result)->toBe('test_passed');
            });

            it('fails when state condition is not met', function () {
                $result = (new PipelineBuilder())
                    ->filterWithState(fn($state) => $state->hasTag('NonExistentTag'), 'State condition failed')
                    ->create()
                    ->executeWith(ProcessingState::with('test'));
                
                expect($result->isFailure())->toBeTrue();
                expect($result->exception()->getMessage())->toBe('State condition failed');
            });
        });
    });

    describe('finalization', function () {
        describe('finally with CanFinalizeProcessing', function () {
            it('applies finalizer implementing interface', function () {
                $finalizer = new TestFinalizer();
                
                $result = (new PipelineBuilder())
                    ->finally($finalizer)
                    ->create()
                    ->executeWith(ProcessingState::with('test'))
                    ->value();
                
                expect($result)->toBe('test_finalized');
            });
        });

        describe('finally with callable', function () {
            it('applies callable finalizer', function () {
                $result = (new PipelineBuilder())
                    ->finally(fn($state) => $state->value() . '_final')
                    ->create()
                    ->executeWith(ProcessingState::with('test'))
                    ->value();
                
                expect($result)->toBe('test_final');
            });
        });

        describe('finally with invalid argument', function () {
            it('throws exception for invalid finalizer', function () {
                $builder = new PipelineBuilder();
                expect(fn() => $builder->finally('invalid'))
                    ->toThrow(TypeError::class);
            });
        });
    });
});