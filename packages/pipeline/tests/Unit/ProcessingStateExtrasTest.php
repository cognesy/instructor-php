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
            $result = $state->transform()->map(fn($x) => $x * 2)->get();
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(20);
        });

        it('preserves tags during transformation', function () {
            $tag = new TestTag('test');
            $state = ProcessingState::with(10, [$tag]);
            $result = $state->transform()->map(fn($x) => $x * 2)->get();
            
            expect($result->tags()->only(TestTag::class)->first())->toBe($tag);
            expect($result->value())->toBe(20);
        });

        it('short-circuits on failure', function () {
            $state = ProcessingState::with(null)->withResult(Result::failure(new \Exception('Failed')));
            $executed = false;
            
            $result = $state->transform()
                ->map(function($x) use (&$executed) {
                    $executed = true;
                    return $x * 2;
                })
                ->get();
            
            expect($executed)->toBeFalse();
            expect($result->isFailure())->toBeTrue();
        });

        it('handles transformation exceptions', function () {
            $state = ProcessingState::with(10);
            $result = $state->transform()->map(fn($x) => throw new \RuntimeException('Transform failed'))->get();
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null))->toBeInstanceOf(\RuntimeException::class);
        });
    });

    describe('flatMap()', function () {
        it('applies function returning ProcessingState', function () {
            $state = ProcessingState::with(10);
            $result = $state->transform()->flatMap(fn($x) => ProcessingState::with($x * 2))->get();
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(20);
        });

        it('merges tags from both states', function () {
            $tag1 = new TestTag('original');
            $tag2 = new TestTag('new');
            
            $state = ProcessingState::with(10, [$tag1]);
            $result = $state->transform()->flatMap(fn($x) => ProcessingState::with($x * 2, [$tag2]))->get();
            
            $tags = $result->allTags(TestTag::class);
            expect($tags)->toHaveCount(2);
            expect($tags[0]->name)->toBe('original');
            expect($tags[1]->name)->toBe('new');
        });

        it('short-circuits on original failure', function () {
            $state = ProcessingState::with(null)->withResult(Result::failure(new \Exception('Failed')));
            $executed = false;
            
            $output = $state->transform()->flatMap(function($x) use (&$executed) {
                $executed = true;
                return ProcessingState::with($x * 2);
            })->get();
            
            expect($executed)->toBeFalse();
            expect($output->isFailure())->toBeTrue();
        });

        it('propagates failure from flatMapped function', function () {
            $state = ProcessingState::with(10);
            $result = $state->transform()->flatMap(fn($x) =>
                ProcessingState::with(null)->withResult(Result::failure(new \Exception('FlatMap failed')))
            )->get();
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('FlatMap failed');
        });
    });

    describe('mapResult()', function () {
        it('applies function to Result directly', function () {
            $state = ProcessingState::with(10);
            $result = $state->transform()->map(fn($x) => $x * 2)->get();
            
            expect($result->value())->toBe(20);
        });

        it('preserves tags when mapping Result', function () {
            $tag = new TestTag('test');
            $state = ProcessingState::with(10, [$tag]);
            $result = $state->transform()->map(fn($x) => $x * 2)->get();
            
            expect($result->tags()->only(TestTag::class)->first())->toBe($tag);
        });

        it('handles Result mapping of failures', function () {
            $state = ProcessingState::with(null)->withResult(Result::failure(new \Exception('Failed')));
            $result = $state->transform()->map(fn($x) => $x * 2)->get();
            
            expect($result->isFailure())->toBeTrue();
        });
    });

    describe('filter()', function () {
        it('passes when predicate returns true', function () {
            $state = ProcessingState::with(10);
            $result = $state->transform()->filter(fn($x) => $x > 5)->get();
            
            expect($result->isSuccess())->toBeTrue();
            expect($result->value())->toBe(10);
        });

        it('fails when predicate returns false', function () {
            $state = ProcessingState::with(10);
            $result = $state->transform()->filter(fn($x) => $x > 15)->get();
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('Filter failed');
        });

        it('uses custom error message', function () {
            $state = ProcessingState::with(10);
            $result = $state->transform()->filter(fn($x) => $x > 15, 'Value too small')->get();
            
            expect($result->isFailure())->toBeTrue();
            expect($result->exceptionOr(null)->getMessage())->toBe('Value too small');
        });

        it('short-circuits on failure', function () {
            $state = ProcessingState::with(null)->withResult(Result::failure(new \Exception('Failed')));
            $executed = false;
            
            $result = $state->transform()->filter(function($x) use (&$executed) {
                $executed = true;
                return true;
            })->get();
            
            expect($executed)->toBeFalse();
            expect($result->isFailure())->toBeTrue();
        });

        it('preserves tags', function () {
            $tag = new TestTag('test');
            $state = ProcessingState::with(10, [$tag]);
            $result = $state->transform()->filter(fn($x) => $x > 5)->get();
            
            expect($result->tags()->only(TestTag::class)->first())->toBe($tag);
        });
    });

    describe('monadic composition', function () {
        it('chains multiple operations', function () {
            $state = ProcessingState::with(10)
                ->transform()
                ->map(fn($x) => $x * 2)           // 20
                ->filter(fn($x) => $x > 15)       // passes
                ->map(fn($x) => $x + 5)           // 25
                ->get();
            
            expect($state->isSuccess())->toBeTrue();
            expect($state->value())->toBe(25);
        });

        it('short-circuits on first failure', function () {
            $executed = false;
            
            $state = ProcessingState::with(10)
                ->transform()
                ->map(fn($x) => $x * 2)           // 20
                ->filter(fn($x) => $x > 25)       // fails
                ->map(function($x) use (&$executed) { // should not execute
                    $executed = true;
                    return $x + 5;
                })
                ->get();
            
            expect($executed)->toBeFalse();
            expect($state->isFailure())->toBeTrue();
        });

        it('preserves and accumulates tags through chain', function () {
            $tag1 = new TestTag('start');
            $tag2 = new TestTag('middle');
            
            $state = ProcessingState::with(10, [$tag1])
                ->transform()
                ->map(fn($x) => $x * 2)
                ->flatMap(fn($x) => ProcessingState::with($x + 5, [$tag2]))
                ->get();
            
            $tags = $state->allTags(TestTag::class);
            expect($tags)->toHaveCount(2);
            expect($tags[0]->name)->toBe('start');
            expect($tags[1]->name)->toBe('middle');
            expect($state->value())->toBe(25);
        });
    });
});