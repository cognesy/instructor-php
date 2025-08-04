<?php declare(strict_types=1);

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\Contracts\CanFinalizeProcessing;
use Cognesy\Pipeline\PipelineBuilder;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\TagInterface;

class BuilderTestTag implements TagInterface {
    public function __construct(public readonly string $name) {}
}

class TestMiddleware implements CanControlStateProcessing {
    public function __construct(private string $name) {}
    
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        return $next($state->withTags(new BuilderTestTag($this->name)));
    }
}

class TestFinalizer implements CanFinalizeProcessing {
    public function finalize(ProcessingState $state): mixed {
        return $state->value() . '_finalized';
    }
}

describe('PipelineBuilder Incremental Tests - Missing Coverage', function () {

    describe('source configuration', function () {
        describe('withSource', function () {
            it('sets custom source callable', function () {
                $source = fn() => 'custom_source';
                $builder = new PipelineBuilder();
                
                $result = $builder->withSource($source)->create()->value();
                
                expect($result)->toBe('custom_source');
            });

            it('returns same builder instance', function () {
                $builder = new PipelineBuilder();
                $source = fn() => 'test';
                
                $returned = $builder->withSource($source);
                
                expect($returned)->toBe($builder);
            });
        });

        describe('withInitialValue', function () {
            it('sets static initial value', function () {
                $builder = new PipelineBuilder();
                
                $result = $builder->withInitialValue('static_value')->create()->value();
                
                expect($result)->toBe('static_value');
            });

            it('overwrites previous source', function () {
                $builder = new PipelineBuilder();
                $source = fn() => 'from_source';
                
                $result = $builder
                    ->withSource($source)
                    ->withInitialValue('static_override')
                    ->create()
                    ->value();
                
                expect($result)->toBe('static_override');
            });
        });
    });

    describe('middleware configuration', function () {
        describe('prependMiddleware', function () {
            it('adds middleware at beginning of stack', function () {
                $middleware1 = new TestMiddleware('first');
                $middleware2 = new TestMiddleware('second');
                
                $result = (new PipelineBuilder())
                    ->withInitialValue('test')
                    ->withMiddleware($middleware1)
                    ->prependMiddleware($middleware2)
                    ->create()
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
                    ->withInitialValue(1)
                    ->beforeEach(function($state) use (&$executions) {
                        $executions[] = 'before_' . $state->value();
                        return $state;
                    })
                    ->through(fn($x) => $x * 2)
                    ->through(fn($x) => $x + 1)
                    ->create()
                    ->value();
                
                expect($result)->toBe(3); // (1 * 2) + 1
                expect($executions)->toBe(['before_1', 'before_2']);
            });
        });

        describe('afterEach', function () {
            it('executes hook after each processor', function () {
                $executions = [];
                
                $result = (new PipelineBuilder())
                    ->withInitialValue(1)
                    ->afterEach(function($state) use (&$executions) {
                        $executions[] = 'after_' . $state->value();
                        return $state;
                    })
                    ->through(fn($x) => $x * 2)
                    ->through(fn($x) => $x + 1)
                    ->create()
                    ->value();
                
                expect($result)->toBe(3); // (1 * 2) + 1
                expect($executions)->toBe(['after_2', 'after_3']);
            });
        });

        describe('finishWhen', function () {
            it('stops processing when condition is met', function () {
                $processedSteps = [];
                
                $result = (new PipelineBuilder())
                    ->withInitialValue(1)
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
                    ->withInitialValue(1)
                    ->onFailure(function(ProcessingState $state) use (&$failureHandled, &$failureMessage) {
                        $failureHandled = true;
                        $failureMessage = $state->exception()->getMessage();
                    })
                    ->through(fn($x) => throw new RuntimeException('Test error'))
                    ->create();
                
                expect($result->isFailure())->toBeTrue();
                expect($failureHandled)->toBeTrue();
                expect($failureMessage)->toBe('Test error');
            });
        });

        describe('failWhen', function () {
            it('fails pipeline when condition is met', function () {
                $result = (new PipelineBuilder())
                    ->withInitialValue(10)
                    ->failWhen(fn($state) => $state->value() > 5, 'Value too large')
                    ->through(fn($x) => $x * 2)
                    ->create();
                
                expect($result->isFailure())->toBeTrue();
                expect($result->exception()->getMessage())->toBe('Value too large');
            });

            it('continues when condition is not met', function () {
                $result = (new PipelineBuilder())
                    ->withInitialValue(2)
                    ->failWhen(fn($state) => $state->value() > 5, 'Value too large')
                    ->through(fn($x) => $x * 2)
                    ->create()
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
                    ->withInitialValue(10)
                    ->throughAll(...$callables)
                    ->create()
                    ->value();
                
                expect($result)->toBe(12.5); // ((10 * 2) + 5) / 2
            });
        });

        describe('throughProcessor', function () {
            it('adds processor that implements CanProcessState', function () {
                $processor = new class implements \Cognesy\Pipeline\Contracts\CanProcessState {
                    public function process(ProcessingState $state): ProcessingState {
                        return $state->map(fn($x) => $x . '_processed');
                    }
                };
                
                $result = (new PipelineBuilder())
                    ->withInitialValue('test')
                    ->throughProcessor($processor)
                    ->create()
                    ->value();
                
                expect($result)->toBe('test_processed');
            });
        });

        describe('tapWithState', function () {
            it('executes callback with ProcessingState', function () {
                $capturedState = null;
                
                $result = (new PipelineBuilder())
                    ->withInitialValue('test')
                    ->withTags(new BuilderTestTag('tap_test'))
                    ->tapWithState(function($state) use (&$capturedState) {
                        $capturedState = $state;
                    })
                    ->create()
                    ->value();
                
                expect($result)->toBe('test');
                expect($capturedState)->toBeInstanceOf(ProcessingState::class);
                expect($capturedState->hasTag(BuilderTestTag::class))->toBeTrue();
            });
        });

        describe('filterWithState', function () {
            it('filters based on ProcessingState condition', function () {
                $result = (new PipelineBuilder())
                    ->withInitialValue('test')
                    ->withTags(new BuilderTestTag('filter_test'))
                    ->filterWithState(fn($state) => $state->hasTag(BuilderTestTag::class))
                    ->through(fn($x) => $x . '_passed')
                    ->create()
                    ->value();
                
                expect($result)->toBe('test_passed');
            });

            it('fails when state condition is not met', function () {
                $result = (new PipelineBuilder())
                    ->withInitialValue('test')
                    ->filterWithState(fn($state) => $state->hasTag('NonExistentTag'), 'State condition failed')
                    ->create();
                
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
                    ->withInitialValue('test')
                    ->finally($finalizer)
                    ->create()
                    ->value();
                
                expect($result)->toBe('test_finalized');
            });
        });

        describe('finally with callable', function () {
            it('applies callable finalizer', function () {
                $result = (new PipelineBuilder())
                    ->withInitialValue('test')
                    ->finally(fn($state) => $state->value() . '_final')
                    ->create()
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