<?php declare(strict_types=1);

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;

class TransformTestTag implements TagInterface {
    public function __construct(public readonly string $name) {}
}

class AnotherTransformTag implements TagInterface {
    public function __construct(public readonly string $value) {}
}

describe('TransformQuery', function () {

    describe('terminal operations', function () {
        it('gets ProcessingState with get()', function () {
            $state = ProcessingState::with(42);
            $result = $state;
            expect($result)->toBe($state);
        });

        it('gets Result with getResult()', function () {
            $state = ProcessingState::with(42);
            $result = $state->result();
            
            expect($result->unwrap())->toBe(42);
            expect($result->isSuccess())->toBeTrue();
        });

        it('gets value directly with value()', function () {
            $state = ProcessingState::with('hello');
            $value = $state->value();
            
            expect($value)->toBe('hello');
        });

        it('gets value or default with valueOr()', function () {
            $successState = ProcessingState::with('success');
            $failureState = ProcessingState::with(null)->withResult(Result::failure(new Exception('failed')));
            
            expect($successState->valueOr('default'))->toBe('success');
            expect($failureState->valueOr('default'))->toBe('default');
        });

        it('checks success status with isSuccess()', function () {
            $successState = ProcessingState::with(42);
            $failureState = ProcessingState::with(null)->withResult(Result::failure(new Exception('failed')));
            
            expect($successState->isSuccess())->toBeTrue();
            expect($failureState->isSuccess())->toBeFalse();
        });

        it('checks failure status with isFailure()', function () {
            $successState = ProcessingState::with(42);
            $failureState = ProcessingState::with(null)->withResult(Result::failure(new Exception('failed')));
            
            expect($successState->isFailure())->toBeFalse();
            expect($failureState->isFailure())->toBeTrue();
        });

        it('gets exception with exception()', function () {
            $exception = new Exception('test error');
            $failureState = ProcessingState::with(null)->withResult(Result::failure($exception));
            
            expect($failureState->exception())->toBe($exception);
        });

        it('gets exception or default with exceptionOr()', function () {
            $exception = new Exception('test error');
            $successState = ProcessingState::with(42);
            $failureState = ProcessingState::with(null)->withResult(Result::failure($exception));
            
            expect($successState->exceptionOr('no error'))->toBe('no error');
            expect($failureState->exceptionOr('no error'))->toBe($exception);
        });
    });

    describe('transformations', function () {
        it('transforms value with map()', function () {
            $state = ProcessingState::with(10);
            $result = $state->map(fn($x) => $x * 2);
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(20);
        });

        it('short-circuits map on failure', function () {
            $executed = false;
            $failureState = ProcessingState::with(null)->withResult(Result::failure(new Exception('failed')));
            
            $result = $failureState
                ->map(function($x) use (&$executed) {
                    $executed = true;
                    return $x * 2;
                });
            
            expect($executed)->toBeFalse();
            expect($result->isFailure())->toBeTrue();
        });

        it('handles map exceptions', function () {
            $state = ProcessingState::with(10);
            $result = $state->map(fn($x) => throw new RuntimeException('Map failed'));
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null))->toBeInstanceOf(RuntimeException::class);
        });

        it('applies map with ProcessingState return', function () {
            $state = ProcessingState::with(10);
            $result = $state->map(fn($x) => ProcessingState::with($x * 3));
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(30);
        });

        it('filters values with filter()', function () {
            $state = ProcessingState::with(15);
            $passed = $state->failWhen(fn($x) => $x > 10);
            $failed = $state->failWhen(fn($x) => $x > 20);
            
            expect($passed->isSuccess())->toBeTrue();
            expect($passed->value())->toBe(15);
            
            expect($failed->isFailure())->toBeTrue();
            expect($failed->exceptionOr(null)->getMessage())->toBe('Failure condition met');
        });

        it('uses custom filter error message', function () {
            $state = ProcessingState::with(5);
            $result = $state->failWhen(fn($x) => $x > 10, 'Value too small');
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('Value too small');
        });
    });

    describe('error handling', function () {
        it('recovers from failure with recover()', function () {
            $failureState = ProcessingState::with(null)->withResult(Result::failure(new Exception('failed')));
            $result = $failureState->recover('recovered');
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe('recovered');
        });

        it('does not recover from success with recover()', function () {
            $successState = ProcessingState::with('original');
            $result = $successState->recover('recovered');
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe('original');
        });

        it('recovers with function using recoverWith()', function () {
            $failureState = ProcessingState::with(null)->withResult(Result::failure(new Exception('failed')));
            $result = $failureState->recoverWith(fn(ProcessingState $state) => 'recovered from: ' . $state->exception()->getMessage());
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe('recovered from: failed');
        });

        it('preserves original failure on recoverWith exception', function () {
            $originalException = new Exception('original error');
            $failureState = ProcessingState::with(null)->withResult(Result::failure($originalException));
            
            $result = $failureState->recoverWith(fn() => throw new Exception('recovery failed'));
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exception())->toBe($originalException);
        });
    });

    describe('tag operations', function () {
        it('adds tags conditionally with addTagsIf()', function () {
            $tag = new TransformTestTag('conditional');
            $state = ProcessingState::with(42);
            
            $withTag = $state->addTagsIf(fn() => true, $tag);
            $withoutTag = $state->addTagsIf(fn() => false, $tag);
            
            expect($withTag->allTags(TransformTestTag::class))->toHaveCount(1);
            expect($withoutTag->allTags(TransformTestTag::class))->toHaveCount(0);
        });

        it('adds tags on success with addTagsIfSuccess()', function () {
            $tag = new TransformTestTag('success');
            $successState = ProcessingState::with(42);
            $failureState = ProcessingState::with(null)->withResult(Result::failure(new Exception('failed')));
            
            $successResult = $successState->addTagsIfSuccess($tag);
            $failureResult = $failureState->addTagsIfSuccess($tag);
            
            expect($successResult->allTags(TransformTestTag::class))->toHaveCount(1);
            expect($failureResult->allTags(TransformTestTag::class))->toHaveCount(0);
        });

        it('adds tags on failure with addTagsIfFailure()', function () {
            $tag = new TransformTestTag('failure');
            $successState = ProcessingState::with(42);
            $failureState = ProcessingState::with(null)->withResult(Result::failure(new Exception('failed')));
            
            $successResult = $successState->addTagsIfFailure($tag);
            $failureResult = $failureState->addTagsIfFailure($tag);
            
            expect($successResult->allTags(TransformTestTag::class))->toHaveCount(0);
            expect($failureResult->allTags(TransformTestTag::class))->toHaveCount(1);
        });

        it('merges from another state with mergeFrom()', function () {
            $tag1 = new TransformTestTag('original');
            $tag2 = new AnotherTransformTag('source');
            
            $originalState = ProcessingState::with('original', [$tag1]);
            $sourceState = ProcessingState::with('source', [$tag2]);
            
            $result = $originalState->mergeFrom($sourceState);
            
            expect($result->value())->toBe('original'); // keeps original value
            expect($result->allTags(TransformTestTag::class))->toHaveCount(1);
            expect($result->allTags(AnotherTransformTag::class))->toHaveCount(1);
        });
    });

    describe('conditional operations', function () {
        it('applies transformation conditionally with when()', function () {
            $state = ProcessingState::with(10);
            
            $applied = $state->when(fn() => true, fn($s) => $s->withResult(Result::success(20)));
            $notApplied = $state->when(fn() => false, fn($s) => $s->withResult(Result::success(20)));
            
            expect($applied->value())->toBe(20);
            expect($notApplied->value())->toBe(10);
        });

        it('applies transformation based on value with whenValue()', function () {
            $state = ProcessingState::with(15);
            
            $applied = $state->when(
                fn($v) => $v > 10,
                fn(ProcessingState $s) => $s->withResult(Result::success('large'))
            );
            $notApplied = $state->whenState(
                fn($v) => $v > 20,
                fn(ProcessingState $s) => $s->withResult(Result::success('large'))
            );
            
            expect($applied->value())->toBe('large');
            expect($notApplied->value())->toBe(15);
        });
    });

    describe('chaining operations', function () {
        it('chains multiple transformations', function () {
            $tag = new TransformTestTag('chained');
            $state = ProcessingState::with(5, [$tag]);
            
            $result = $state->map(fn($x) => $x * 2)      // 10
                ->failWhen(fn($x) => $x > 5)   // passes
                ->map(fn($x) => $x + 3);      // 13

            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(13);
            expect($result->allTags(TransformTestTag::class))->toHaveCount(1);
        });

        it('short-circuits on first failure in chain', function () {
            $executed = false;
            
            $result = ProcessingState::with(3)
                ->map(fn($x) => $x * 2)           // 6
                ->failWhen(fn($x) => $x > 10)       // fails
                ->map(function($x) use (&$executed) { // should not execute
                    $executed = true;
                    return $x + 1;
                });
            
            expect($executed)->toBeFalse();
            expect($result->isFailure())->toBeTrue();
        });
    });
});