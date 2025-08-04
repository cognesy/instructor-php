<?php declare(strict_types=1);

use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\TagInterface;
use Cognesy\Utils\Result\Result;

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
            
            expect($newState->firstTag(StateTag::class))->toBe($tag);
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
            
            expect($failedState->hasTag(\Cognesy\Pipeline\Tag\ErrorTag::class))->toBeTrue();
        });
    });

    describe('withoutTags', function () {
        it('removes tags of specified class', function () {
            $tag1 = new StateTag('keep');
            $tag2 = new AnotherStateTag('remove');
            $state = ProcessingState::with(10, [$tag1, $tag2]);
            
            $newState = $state->withoutTags(AnotherStateTag::class);
            
            expect($newState->hasTag(StateTag::class))->toBeTrue();
            expect($newState->hasTag(AnotherStateTag::class))->toBeFalse();
        });

        it('removes all tags of multiple classes', function () {
            $tag1 = new StateTag('remove1');
            $tag2 = new AnotherStateTag('remove2');
            $state = ProcessingState::with(10, [$tag1, $tag2]);
            
            $newState = $state->withoutTags(StateTag::class, AnotherStateTag::class);
            
            expect($newState->countTag())->toBe(0);
        });
    });

    describe('mergeFrom', function () {
        it('merges tags from source state', function () {
            $tag1 = new StateTag('original');
            $tag2 = new AnotherStateTag('source');
            $originalState = ProcessingState::with(10, [$tag1]);
            $sourceState = ProcessingState::with(20, [$tag2]);
            
            $merged = $originalState->mergeFrom($sourceState);
            
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
            
            $merged = $sourceState->mergeInto($targetState);
            
            expect($merged->value())->toBe(10); // Keeps source result
            expect($merged->hasTag(StateTag::class))->toBeTrue();
            expect($merged->hasTag(AnotherStateTag::class))->toBeTrue();
        });
    });

    describe('combine', function () {
        it('combines states with default result combinator', function () {
            $state1 = ProcessingState::with(10);
            $state2 = ProcessingState::with(20);
            
            $combined = $state1->combine($state2);
            
            expect($combined->value())->toBe(20); // Second result wins by default
        });

        it('combines states with custom result combinator', function () {
            $state1 = ProcessingState::with(10);
            $state2 = ProcessingState::with(20);
            
            $combined = $state1->combine($state2, fn($a, $b) => Result::success($a->unwrap() + $b->unwrap()));
            
            expect($combined->value())->toBe(30);
        });

        it('combines tags from both states', function () {
            $tag1 = new StateTag('first');
            $tag2 = new AnotherStateTag('second');
            $state1 = ProcessingState::with(10, [$tag1]);
            $state2 = ProcessingState::with(20, [$tag2]);
            
            $combined = $state1->combine($state2);
            
            expect($combined->hasTag(StateTag::class))->toBeTrue();
            expect($combined->hasTag(AnotherStateTag::class))->toBeTrue();
        });
    });

    describe('tag operations', function () {

        describe('lastTag', function () {
            it('returns last tag of specified class', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                $lastTag = $state->lastTag(StateTag::class);
                
                expect($lastTag)->toBe($tag2);
            });

            it('returns null when no tag of class exists', function () {
                $state = ProcessingState::with(10);
                
                expect($state->lastTag(StateTag::class))->toBeNull();
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
                
                $result = $state->hasAllOfTags([$tag1, $tag3]);
                
                expect($result)->toBeTrue();
            });

            it('returns false when some tag classes missing', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $state = ProcessingState::with(10, [$tag1, $tag2]);
                
                // This should return false because AnotherStateTag class is not present
                $missingTag = new AnotherStateTag('missing');
                $result = $state->hasAllOfTags([$tag1, $missingTag]);
                
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
                $result = $state->hasAnyOfTags([$missingTag, $tag1]);
                
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
                $result = $state->hasAnyOfTags([$missingTag1, $missingTag2]);
                
                expect($result)->toBeFalse();
            });
        });

        describe('countTag', function () {
            it('counts all tags when no class specified', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                expect($state->countTag())->toBe(3);
            });

            it('counts tags of specific class', function () {
                $tag1 = new StateTag('first');
                $tag2 = new StateTag('second');
                $tag3 = new AnotherStateTag('different');
                $state = ProcessingState::with(10, [$tag1, $tag2, $tag3]);
                
                expect($state->countTag(StateTag::class))->toBe(2);
                expect($state->countTag(AnotherStateTag::class))->toBe(1);
            });
        });
    });

    describe('result accessors', function () {
        describe('result', function () {
            it('returns the Result object', function () {
                $state = ProcessingState::with(42);
                
                $result = $state->result();
                
                expect($result)->toBeInstanceOf(Result::class);
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