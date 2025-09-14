<?php declare(strict_types=1);

use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\TagMap\Contracts\TagInterface;

class StateTag implements TagInterface {
    public function __construct(public readonly string $label) {}
}

class AnotherStateTag implements TagInterface {
    public function __construct(public readonly string $value) {}
}

describe('ProcessingState Incremental Tests - Missing Coverage', function () {

    describe('withResult', function () {
        it('creates new state with different result', function () {
            $originalState = ProcessingState::with(10);
            $newResult = Result::success(20);
            
            $newState = $originalState->withResult($newResult);
            
            expect($newState->value())->toBe(20);
            expect($originalState->value())->toBe(10); // Original unchanged
        });

        it('preserves tags when changing result', function () {
            $tag = new StateTag('test');
            $originalState = ProcessingState::with(10, [$tag]);
            $newResult = Result::success(20);
            
            $newState = $originalState->withResult($newResult);
            
            expect($newState->tags()->only(StateTag::class)->first())->toBe($tag);
        });
    });

    describe('failWith', function () {
        it('creates failed state with exception', function () {
            $state = ProcessingState::with(10);
            $exception = new RuntimeException('Test failure');
            
            $failedState = $state->failWith($exception);
            
            expect($failedState->isFailure())->toBeTrue();
            expect($failedState->exception())->toBe($exception);
        });

        it('adds ErrorTag when failing', function () {
            $state = ProcessingState::with(10);
            $exception = new RuntimeException('Test failure');
            
            $failedState = $state->failWith($exception);
            
            expect($failedState->hasTag(\Cognesy\Utils\TagMap\Tags\ErrorTag::class))->toBeTrue();
        });
    });

    describe('mergeFrom', function () {
        it('merges tags from source state', function () {
            $tag1 = new StateTag('original');
            $tag2 = new AnotherStateTag('source');
            $originalState = ProcessingState::with(10, [$tag1]);
            $sourceState = ProcessingState::with(20, [$tag2]);
            
            $merged = $originalState->transform()->mergeFrom($sourceState);
            
            expect($merged->value())->toBe(10); // Keeps original result
            expect($merged->hasTag(StateTag::class))->toBeTrue();
            expect($merged->hasTag(AnotherStateTag::class))->toBeTrue();
        });
    });

    describe('mergeInto', function () {
        it('merges tags into target state', function () {
            $tag1 = new StateTag('source');
            $tag2 = new AnotherStateTag('target');
            $sourceState = ProcessingState::with(10, [$tag1]);
            $targetState = ProcessingState::with(20, [$tag2]);
            
            $merged = $sourceState->transform()->mergeInto($targetState);
            
            expect($merged->value())->toBe(10); // Keeps source result
            expect($merged->hasTag(StateTag::class))->toBeTrue();
            expect($merged->hasTag(AnotherStateTag::class))->toBeTrue();
        });
    });

    describe('tag operations', function () {

        describe('lastTag', function () {
            it('returns last tag of specified class', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                $lastTag = $state->tags()->only(StateTag::class)->last();
                
                expect($lastTag)->toBe($tag2);
            });

            it('returns null when no tag of class exists', function () {
                $state = ProcessingState::with(10);
                
                expect($state->tags()->only(StateTag::class)->last())->toBeNull();
            });
        });

        describe('hasTag', function () {
            it('returns true when tag class exists', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second'); 
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                expect($state->hasTag(StateTag::class))->toBeTrue();
                expect($state->hasTag(AnotherStateTag::class))->toBeTrue();
            });

            it('returns false when tag class does not exist', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                expect($state->hasTag('NonExistentTag'))->toBeFalse();
            });
        });

        describe('hasAllOfTags', function () {
            it('returns true when all tag instances exist', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                $result = $state->tags()->hasAll($tag1, $tag2);
                
                expect($result)->toBeTrue();
            });

            it('returns false when some tag classes missing', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $state = ProcessingState::with(10, [$tag1, $tag2]);
                
                // This should return false because AnotherStateTag class is not present
                $missingTag = new AnotherStateTag('missing');
                $result = $state->tags()->hasAll($tag1, $missingTag);
                
                expect($result)->toBeFalse();
            });
        });

        describe('hasAnyOfTags', function () {
            it('returns true when any tag instance exists', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                $missingTag = new StateTag('missing');
                $result = $state->tags()->hasAny($missingTag, $tag1);
                
                expect($result)->toBeTrue();
            });

            it('returns false when no tag classes exist', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $state = ProcessingState::with(10, [$tag1, $tag2]);
                
                // State only has StateTag, so checking for AnotherStateTag should return false
                $missingTag1 = new AnotherStateTag('missing1');
                $missingTag2 = new AnotherStateTag('missing2');
                // Create a completely different tag class for this test
                $result = $state->tags()->hasAny($missingTag1, $missingTag2);
                
                expect($result)->toBeFalse();
            });
        });

        describe('countTag', function () {
            it('counts all tags when no class specified', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                expect($state->tags()->count())->toBe(3);
            });

            it('counts tags of specific class', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                expect($state->tags()->only(StateTag::class)->count())->toBe(2);
                expect($state->tags()->only(AnotherStateTag::class)->count())->toBe(1);
            });
        });
    });

    describe('result accessors', function () {
        describe('result', function () {
            it('returns the Result object', function () {
                $state = ProcessingState::with(42);
                
                $result = $state->result();
                
                expect($result)->toBeInstanceOf(Success::class);
                expect($result->unwrap())->toBe(42);
            });
        });

        describe('exception', function () {
            it('returns exception from failed result', function () {
                $exception = new RuntimeException('Test error');
                $state = ProcessingState::empty()->failWith($exception);
                
                expect($state->exception())->toBe($exception);
            });

            it('throws when called on successful result', function () {
                $state = ProcessingState::with(42);
                
                expect(fn() => $state->exception())->toThrow(RuntimeException::class);
            });
        });

        describe('exceptionOr', function () {
            it('returns exception from failed result', function () {
                $exception = new RuntimeException('Test error');
                $state = ProcessingState::empty()->failWith($exception);
                
                expect($state->exceptionOr('default'))->toBe($exception);
            });

            it('returns default for successful result', function () {
                $state = ProcessingState::with(42);
                
                expect($state->exceptionOr('default'))->toBe('default');
            });
        });
    });
});