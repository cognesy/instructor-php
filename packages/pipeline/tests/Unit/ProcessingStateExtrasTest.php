<?php declare(strict_types=1);

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;

class TestTag implements TagInterface {
    public function __construct(public readonly string $name) {}
}

describe('ProcessingState Monadic Operations', function () {
    
    describe('map()', function () {
        it('applies function to successful value', function () {
            $state = ProcessingState::with(10);
            $result = $state->map(fn($x) => $x * 2);
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(20);
        });

        it('preserves tags during transformation', function () {
            $tag = new TestTag('test');
            $state = ProcessingState::with(10, [$tag]);
            $result = $state->map(fn($x) => $x * 2);
            
            expect($result->tags()->only(TestTag::class)->first())->toBe($tag);
            expect($result->value())->toBe(20);
        });

        it('short-circuits on failure', function () {
            $state = ProcessingState::with(null)->withResult(Result::failure(new \Exception('Failed')));
            $executed = false;
            
            $result = $state
                ->map(function($x) use (&$executed) {
                    $executed = true;
                    return $x * 2;
                });

            
            expect($executed)->toBeFalse();
            expect($result->isFailure())->toBeTrue();
        });

        it('handles transformation exceptions', function () {
            $state = ProcessingState::with(10);
            $result = $state->map(fn($x) => throw new \RuntimeException('Transform failed'));
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null))->toBeInstanceOf(\RuntimeException::class);
        });
    });

    describe('map()', function () {
        it('applies function returning ProcessingState', function () {
            $state = ProcessingState::with(10);
            $result = $state->map(fn($x) => ProcessingState::with($x * 2));
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(20);
        });

        it('merges tags from both states', function () {
            $tag1 = new TestTag('original');
            $tag2 = new TestTag('new');
            
            $state = ProcessingState::with(10, [$tag1]);
            $result = $state->map(fn($x) => ProcessingState::with($x * 2, [$tag2]));
            
            $tags = $result->allTags(TestTag::class);
            expect($tags)->toHaveCount(2);
            expect($tags[0]->name)->toBe('original');
            expect($tags[1]->name)->toBe('new');
        });

        it('short-circuits on original failure', function () {
            $state = ProcessingState::with(null)->withResult(Result::failure(new \Exception('Failed')));
            $executed = false;
            
            $output = $state->map(function($x) use (&$executed) {
                $executed = true;
                return ProcessingState::with($x * 2);
            });
            
            expect($executed)->toBeFalse();
            expect($output->isFailure())->toBeTrue();
        });

        it('propagates failure from mapped function', function () {
            $state = ProcessingState::with(10);
            $result = $state->map(fn($x) =>
                ProcessingState::with(null)->withResult(Result::failure(new \Exception('map failed')))
            );
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('map failed');
        });
    });

    describe('mapResult()', function () {
        it('applies function to Result directly', function () {
            $state = ProcessingState::with(10);
            $result = $state->map(fn($x) => $x * 2);
            
            expect($result->value())->toBe(20);
        });

        it('preserves tags when mapping Result', function () {
            $tag = new TestTag('test');
            $state = ProcessingState::with(10, [$tag]);
            $result = $state->map(fn($x) => $x * 2);
            
            expect($result->tags()->only(TestTag::class)->first())->toBe($tag);
        });

        it('handles Result mapping of failures', function () {
            $state = ProcessingState::with(null)->withResult(Result::failure(new \Exception('Failed')));
            $result = $state->map(fn($x) => $x * 2);
            
            expect($result->isFailure())->toBeTrue();
        });
    });

    describe('filter()', function () {
        it('passes when predicate returns true', function () {
            $state = ProcessingState::with(10);
            $result = $state->failWhen(fn($x) => $x > 5);
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(10);
        });

        it('fails when predicate returns false', function () {
            $state = ProcessingState::with(10);
            $result = $state->failWhen(fn($x) => $x > 15);
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('Failure condition met');
        });

        it('uses custom error message', function () {
            $state = ProcessingState::with(10);
            $result = $state->failWhen(fn($x) => $x > 15, 'Value too small');
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('Value too small');
        });

        it('short-circuits on failure', function () {
            $state = ProcessingState::with(null)->withResult(Result::failure(new \Exception('Failed')));
            $executed = false;
            
            $result = $state->failWhen(function($x) use (&$executed) {
                $executed = true;
                return true;
            });
            
            expect($executed)->toBeFalse();
            expect($result->isFailure())->toBeTrue();
        });

        it('preserves tags', function () {
            $tag = new TestTag('test');
            $state = ProcessingState::with(10, [$tag]);
            $result = $state->failWhen(fn($x) => $x > 5);
            
            expect($result->tags()->only(TestTag::class)->first())->toBe($tag);
        });
    });

    describe('monadic composition', function () {
        it('chains multiple operations', function () {
            $state = ProcessingState::with(10)
                ->map(fn($x) => $x * 2)           // 20
                ->failWhen(fn($x) => $x > 15)     // passes
                ->map(fn($x) => $x + 5);          // 25

            expect($state->isSuccess())->toBeTrue();
            expect($state->value())->toBe(25);
        });

        it('short-circuits on first failure', function () {
            $executed = false;
            
            $state = ProcessingState::with(10)
                ->map(fn($x) => $x * 2)           // 20
                ->failWhen(fn($x) => $x > 25)       // fails
                ->map(function($x) use (&$executed) { // should not execute
                    $executed = true;
                    return $x + 5;
                });
            
            expect($executed)->toBeFalse();
            expect($state->isFailure())->toBeTrue();
        });

        it('preserves and accumulates tags through chain', function () {
            $tag1 = new TestTag('start');
            $tag2 = new TestTag('middle');
            
            $state = ProcessingState::with(10, [$tag1])
                ->map(fn($x) => $x * 2)
                ->map(fn($x) => ProcessingState::with($x + 5, [$tag2]));
            
            $tags = $state->allTags(TestTag::class);
            expect($tags)->toHaveCount(2);
            expect($tags[0]->name)->toBe('start');
            expect($tags[1]->name)->toBe('middle');
            expect($state->value())->toBe(25);
        });
    });
});