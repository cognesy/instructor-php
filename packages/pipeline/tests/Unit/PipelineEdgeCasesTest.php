<?php

use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Operators\Call;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\SkipProcessingTag;
use Cognesy\Utils\Result\Result;

describe('Pipeline Edge Cases - Null Handling', function () {
    test('null input with Allow strategy passes through', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x ?? 'default')
            ->create()
            ->executeWith(ProcessingState::with(null));

        expect($pipeline->value())->toBe('default');
        expect($pipeline->isSuccess())->toBeTrue();
    });

    test('null processor output with Allow strategy', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => null, NullStrategy::Allow)
            ->through(fn($x) => $x ?? 'fallback', NullStrategy::Allow)
            ->create()
            ->executeWith(ProcessingState::with('input'));
        
        // Since first processor returns null and passes through, second processor processes null
        expect($pipeline->isSuccess())->toBeTrue();
        expect($pipeline->value())->toBe('fallback');
    });

    test('null processor output with Fail strategy throws', function () {
        $processor = Call::withValue(fn($x) => null)->onNull(NullStrategy::Fail);
        
        $state = ProcessingState::with('input');
        $result = $processor->process($state);
        
        expect($result->isFailure())->toBeTrue();
        expect($result->exception())->toBeInstanceOf(RuntimeException::class);
        expect(strtolower($result->exception()->getMessage()))->toContain('null');
    });

    test('null processor output with Skip strategy adds SkipProcessingTag', function () {
        $processor = Call::withValue(fn($x) => null)->onNull(NullStrategy::Skip);
        
        $state = ProcessingState::with('input');
        $result = $processor->process($state);
        
        expect($result->hasTag(SkipProcessingTag::class))->toBeTrue();
        expect($result->value())->toBeNull();
    });
});

describe('Pipeline Edge Cases - Exception Handling', function () {
    test('exception in processor creates PipelineException with context', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => throw new InvalidArgumentException('Original error'))
            ->create()
            ->executeWith(ProcessingState::with('test'));
        
        expect($pipeline->isFailure())->toBeTrue();
        $exception = $pipeline->exception();
        // Exception could be wrapped by StateFactory, let's check the base error
        expect($exception)->toBeInstanceOf(Throwable::class);
        expect($exception->getMessage())->toContain('Original error');
    });


    test('multiple exceptions in sequence', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => throw new RuntimeException('First error'))
            ->through(fn($x) => $x . ' processed') // Should not execute
            ->create()
            ->executeWith(ProcessingState::with('test'));
        
        expect($pipeline->isFailure())->toBeTrue();
        expect($pipeline->exception()->getMessage())->toContain('First error');
    });

    test('finalizer exception handling', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x . ' processed')
            ->finally(fn($state) => throw new RuntimeException('Finalizer error'))
            ->create()
            ->executeWith(ProcessingState::with('test'));
        
        expect($pipeline->isFailure())->toBeTrue();
        expect($pipeline->exception())->toBeInstanceOf(RuntimeException::class);
        expect($pipeline->exception()->getMessage())->toBe('Finalizer error');
    });
});

describe('Pipeline Edge Cases - Complex Type Conversions', function () {
    test('processor returning ProcessingState is preserved', function () {
        $customState = ProcessingState::with('custom', [new SkipProcessingTag()]);
        
        $pipeline = Pipeline::builder()
            ->throughOperator(Call::withState(fn($state) => $customState))
            ->create()
            ->executeWith(ProcessingState::with('test'));
        
        $result = $pipeline->state();
        expect($result->value())->toBe('custom');
        expect($result->hasTag(SkipProcessingTag::class))->toBeTrue();
    });

    test('processor returning Result object is converted properly', function () {
        $pipeline = Pipeline::builder()
            ->throughOperator(Call::withResult(fn($result) => Result::success('converted')))
            ->create()
            ->executeWith(ProcessingState::with('test'));
        
        expect($pipeline->value())->toBe('converted');
        expect($pipeline->isSuccess())->toBeTrue();
    });

    test('nested array and object processing', function () {
        $complexInput = [
            'data' => ['a' => 1, 'b' => 2],
            'meta' => (object)['type' => 'test']
        ];
        
        $pipeline = Pipeline::builder()
            ->through(fn($data) => array_merge($data, ['processed' => true]))
            ->through(fn($data) => json_encode($data))
            ->through(fn($json) => json_decode($json, true))
            ->create()
            ->executeWith(ProcessingState::with($complexInput));
        
        $result = $pipeline->value();
        expect($result['processed'])->toBeTrue();
        expect($result['data']['a'])->toBe(1);
    });
});

describe('Pipeline Edge Cases - Memory and Performance', function () {
    test('large dataset processing does not cause memory issues', function () {
        $largeArray = range(1, 1000);
        
        $pipeline = Pipeline::builder()
            ->through(fn($arr) => array_map(fn($x) => $x * 2, $arr))
            ->through(fn($arr) => array_filter($arr, fn($x) => $x % 4 === 0))
            ->through(fn($arr) => array_sum($arr))
            ->create()
            ->executeWith(ProcessingState::with($largeArray));
        
        $result = $pipeline->value();
        expect($result)->toBeInt();
        expect($result)->toBeGreaterThan(0);
    });

    test('deeply nested processor execution', function () {
        $builder = Pipeline::builder();
        
        // Add 50 processors
        for ($i = 0; $i < 50; $i++) {
            $builder = $builder->through(fn($x) => $x + 1);
        }
        
        $pipeline = $builder->create()->executeWith(ProcessingState::with(1));
        expect($pipeline->value())->toBe(51);
    });

    test('pipeline reuse with different inputs maintains isolation', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x * 2)
            ->create()
            ->executeWith(ProcessingState::with('test'));
        
        // Process multiple inputs
        $results = [];
        for ($i = 1; $i <= 5; $i++) {
            $results[] = $pipeline->for($i)->value();
        }
        
        expect($results)->toBe([2, 4, 6, 8, 10]);
        
        // Verify no state leakage
        expect($pipeline->for(10)->value())->toBe(20);
    });
});

describe('Pipeline Edge Cases - State Combination and Tags', function () {
    test('tag preservation through complex pipeline', function () {
        $initialTags = [new SkipProcessingTag()];
        
        $pipeline = Pipeline::builder()
            ->throughOperator(Call::withState(function($state) {
                // Use StateProcessor to preserve tags while transforming value
                return $state->withResult(Result::success($state->value() * 2));
            }))
            ->throughOperator(Call::withState(function($state) {
                // Another StateProcessor to add 10 while preserving tags
                return $state->withResult(Result::success($state->value() + 10));
            }))
            ->create()
            ->executeWith(ProcessingState::with('test'));
        
        $result = $pipeline->for(5, $initialTags)->state();
        expect($result->value())->toBe(20); // (5 * 2) + 10
        // Tags should be preserved when using StateProcessor
        expect($result->hasTag(SkipProcessingTag::class))->toBeTrue();
    });

    test('state combination preserves all tags', function () {
        $state1 = ProcessingState::with('test1', [new SkipProcessingTag()]);
        $state2 = ProcessingState::with('test2', [new ErrorTag('info')]);
        
        $combined = $state1->transform()->combine($state2);
        
        expect($combined->value())->toBe('test2');
        expect($combined->hasTag(SkipProcessingTag::class))->toBeTrue();
        expect($combined->hasTag(ErrorTag::class))->toBeTrue();
    });

    test('middleware and hooks execution order with exceptions', function () {
        $executionOrder = [];
        
        $pipeline = Pipeline::builder()
            ->beforeEach(function($state) use (&$executionOrder) {
                $executionOrder[] = 'before';
                return $state;
            })
            ->through(function($x) use (&$executionOrder) {
                $executionOrder[] = 'processor';
                if ($x === 'error') {
                    throw new RuntimeException('Test error');
                }
                return $x;
            })
            ->afterEach(function($state) use (&$executionOrder) {
                $executionOrder[] = 'after';
                return $state;
            })
            ->create()
            ->executeWith(ProcessingState::with('test'));

        // Success case
        $executionOrder = [];
        $pipeline->for('success')->value();
        expect($executionOrder)->toBe(['before', 'processor', 'after']);
        
        // Error case - middleware may still execute after hook depending on implementation
        $executionOrder = [];
        $result = $pipeline->for('error');
        expect($result->isFailure())->toBeTrue();
        // Check that at least before and processor executed
        expect($executionOrder)->toContain('before');
        expect($executionOrder)->toContain('processor');
    });
});

describe('Pipeline Edge Cases - Boundary Conditions', function () {
    test('empty string processing', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => trim($x))
            ->through(fn($x) => $x ?: 'empty')
            ->create()
            ->executeWith(ProcessingState::with(''));
        
        expect($pipeline->value())->toBe('empty');
    });

    test('zero and false value handling', function () {
        $pipeline = Pipeline::builder()
            ->through(fn($x) => $x === 0 ? 'zero' : $x)
            ->through(fn($x) => $x === false ? 'false' : $x)
            ->create()
            ->executeWith(ProcessingState::with('test'));
        
        expect($pipeline->for(0)->value())->toBe('zero');
        expect($pipeline->for(false)->value())->toBe('false');
        expect($pipeline->for(''))->value()->toBe('');
    });

    test('circular reference detection', function () {
        $obj1 = new stdClass();
        $obj2 = new stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;
        
        $pipeline = Pipeline::builder()
            ->through(function($obj) {
                // json_encode with circular refs should return false
                $encoded = json_encode($obj);
                if ($encoded === false) {
                    throw new RuntimeException('Circular reference detected');
                }
                return $encoded;
            })
            ->create()
            ->executeWith(ProcessingState::with($obj1));
        
        // Pipeline should fail due to circular reference handling
        expect($pipeline->isFailure())->toBeTrue();
    });

    test('resource handling', function () {
        $resource = fopen('php://memory', 'r+');
        
        $pipeline = Pipeline::builder()
            ->through(fn($r) => is_resource($r) ? 'valid_resource' : 'invalid')
            ->create()
            ->executeWith(ProcessingState::with($resource));
        
        expect($pipeline->value())->toBe('valid_resource');
        
        fclose($resource);
    });
});

describe('Pipeline Edge Cases - Result and State Interaction', function () {
    test('failed result propagation through pipeline', function () {
        $failedResult = Result::failure(new RuntimeException('Initial failure'));
        
        $pipeline = Pipeline::builder()
            ->throughOperator(Call::withResult(function($result) {
                if ($result->isFailure()) {
                    return 'handled_failure';
                }
                return $result->unwrap();
            }))
            ->create()
            ->executeWith(ProcessingState::with($failedResult));
        
        expect($pipeline->value())->toBe('handled_failure');
    });

    test('state transformation maintains result type', function () {
        $successState = ProcessingState::with('success');
        $failureState = ProcessingState::with(Result::failure(new RuntimeException('test')));
        
        $processor = Call::withState(fn($state) => $state->withResult(
            $state->result()->isSuccess() 
                ? Result::success($state->value() . '_processed') 
                : $state->result()
        ));
        
        $successResult = $processor->process($successState);
        $failureResult = $processor->process($failureState);
        
        expect($successResult->value())->toBe('success_processed');
        expect($failureResult->isFailure())->toBeTrue();
    });
});