<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\TagInterface;
use Cognesy\Pipeline\TagMap;
use Cognesy\Utils\Result\Result;

// Test tags for unit testing
class UnitTagA implements TagInterface
{
    public function __construct(public readonly string $value) {}
}

class UnitTagB implements TagInterface
{
    public function __construct(public readonly int $number) {}
}

class UnitTagC implements TagInterface
{
    public function __construct(public readonly array $data) {}
}

describe('Computation Unit Tests', function () {
    describe('Construction', function () {
        it('constructs with Result and tags', function () {
            $result = Result::success('test data');
            $tags = [new UnitTagA('test')];
            
            $computation = new Computation($result, TagMap::create($tags));
            
            expect($computation->result())->toBe($result);
            expect($computation->count())->toBe(1);
        });

        it('wraps mixed values with wrap()', function () {
            $computation = Computation::wrap('simple string');
            
            expect($computation->result()->unwrap())->toBe('simple string');
            expect($computation->result()->isSuccess())->toBeTrue();
        });

        it('wraps Result values directly', function () {
            $result = Result::failure(new Exception('test error'));
            $computation = Computation::wrap($result);
            
            expect($computation->result())->toBe($result);
            expect($computation->result()->isFailure())->toBeTrue();
        });

        it('wraps with initial tags', function () {
            $tags = [new UnitTagA('initial'), new UnitTagB(42)];
            $computation = Computation::wrap('data', $tags);
            
            expect($computation->count())->toBe(2);
            expect($computation->has(UnitTagA::class))->toBeTrue();
            expect($computation->has(UnitTagB::class))->toBeTrue();
        });
    });

    describe('Result Access', function () {
        it('returns the wrapped Result', function () {
            $result = Result::success(['key' => 'value']);
            $computation = new Computation($result);
            
            expect($computation->result())->toBe($result);
            expect($computation->result()->unwrap())->toBe(['key' => 'value']);
        });
    });

    describe('Tag Management', function () {
        it('adds tags with with()', function () {
            $computation = Computation::wrap('data');
            $tagA = new UnitTagA('test');
            $tagB = new UnitTagB(123);
            
            $newComputation = $computation->with($tagA, $tagB);
            
            expect($newComputation->count())->toBe(2);
            expect($newComputation->has(UnitTagA::class))->toBeTrue();
            expect($newComputation->has(UnitTagB::class))->toBeTrue();
            
            // Original unchanged (immutability)
            expect($computation->count())->toBe(0);
        });

        it('adds multiple tags of same type', function () {
            $computation = Computation::wrap('data');
            $tag1 = new UnitTagA('first');
            $tag2 = new UnitTagA('second');
            
            $newComputation = $computation->with($tag1, $tag2);
            
            expect($newComputation->count(UnitTagA::class))->toBe(2);
            expect($newComputation->first(UnitTagA::class)->value)->toBe('first');
            expect($newComputation->last(UnitTagA::class)->value)->toBe('second');
        });

        it('removes tags with without()', function () {
            $computation = Computation::wrap('data', [
                new UnitTagA('keep'),
                new UnitTagB(123),
                new UnitTagC(['remove' => 'me'])
            ]);
            
            $filtered = $computation->without(UnitTagB::class, UnitTagC::class);
            
            expect($filtered->count())->toBe(1);
            expect($filtered->has(UnitTagA::class))->toBeTrue();
            expect($filtered->has(UnitTagB::class))->toBeFalse();
            expect($filtered->has(UnitTagC::class))->toBeFalse();
        });

        it('replaces message with withMessage()', function () {
            $computation = Computation::wrap('original', [new UnitTagA('keep')]);
            $newResult = Result::success('replaced');
            
            $newComputation = $computation->withResult($newResult);
            
            expect($newComputation->result()->unwrap())->toBe('replaced');
            expect($newComputation->count())->toBe(1); // Tags preserved
            expect($newComputation->has(UnitTagA::class))->toBeTrue();
        });
    });

    describe('Tag Queries', function () {
        beforeEach(function () {
            $this->computation = Computation::wrap('data', [
                new UnitTagA('first'),
                new UnitTagB(10),
                new UnitTagA('second'),
                new UnitTagB(20),
                new UnitTagC(['test' => 'data']),
                new UnitTagA('third')
            ]);
        });

        it('returns all tags with all()', function () {
            // Test getting all tags by counting by type
            expect($this->computation->count())->toBe(6);
            expect($this->computation->count(UnitTagA::class))->toBe(3);
            expect($this->computation->count(UnitTagB::class))->toBe(2);
            expect($this->computation->count(UnitTagC::class))->toBe(1);
        });

        it('returns filtered tags with all(tagClass)', function () {
            $tagAs = $this->computation->all(UnitTagA::class);
            expect(count($tagAs))->toBe(3);
            expect($tagAs[0]->value)->toBe('first');
            expect($tagAs[2]->value)->toBe('third');
        });

        it('returns first tag with first()', function () {
            $first = $this->computation->first(UnitTagA::class);
            expect($first)->toBeInstanceOf(UnitTagA::class);
            expect($first->value)->toBe('first');
        });

        it('returns last tag with last()', function () {
            $last = $this->computation->last(UnitTagA::class);
            expect($last)->toBeInstanceOf(UnitTagA::class);
            expect($last->value)->toBe('third');
        });

        it('returns null for non-existent tags', function () {
            $nonExistent = $this->computation->first('NonExistentTag');
            expect($nonExistent)->toBeNull();
            
            $nonExistent = $this->computation->last('NonExistentTag');
            expect($nonExistent)->toBeNull();
        });

        it('checks tag existence with has()', function () {
            expect($this->computation->has(UnitTagA::class))->toBeTrue();
            expect($this->computation->has(UnitTagB::class))->toBeTrue();
            expect($this->computation->has('NonExistentTag'))->toBeFalse();
        });

        it('counts tags with count()', function () {
            expect($this->computation->count())->toBe(6);
            expect($this->computation->count(UnitTagA::class))->toBe(3);
            expect($this->computation->count(UnitTagB::class))->toBe(2);
            expect($this->computation->count(UnitTagC::class))->toBe(1);
            expect($this->computation->count('NonExistentTag'))->toBe(0);
        });
    });

    describe('Immutability', function () {
        it('preserves original computation when adding tags', function () {
            $original = Computation::wrap('data', [new UnitTagA('original')]);
            $modified = $original->with(new UnitTagB(123));
            
            expect($original->count())->toBe(1);
            expect($original->has(UnitTagB::class))->toBeFalse();
            
            expect($modified->count())->toBe(2);
            expect($modified->has(UnitTagB::class))->toBeTrue();
        });

        it('preserves original computation when removing tags', function () {
            $original = Computation::wrap('data', [
                new UnitTagA('keep'),
                new UnitTagB(123)
            ]);
            $modified = $original->without(UnitTagB::class);
            
            expect($original->count())->toBe(2);
            expect($original->has(UnitTagB::class))->toBeTrue();
            
            expect($modified->count())->toBe(1);
            expect($modified->has(UnitTagB::class))->toBeFalse();
        });

        it('preserves original computation when replacing message', function () {
            $original = Computation::wrap('original');
            $modified = $original->withResult(Result::success('modified'));
            
            expect($original->result()->unwrap())->toBe('original');
            expect($modified->result()->unwrap())->toBe('modified');
        });
    });

    describe('Edge Cases', function () {
        it('handles empty tag arrays', function () {
            $computation = Computation::wrap('data', []);
            
            expect($computation->count())->toBe(0);
            expect($computation->has(UnitTagA::class))->toBeFalse();
        });

        it('handles null values in Result', function () {
            $computation = Computation::wrap(null);
            
            expect($computation->result()->unwrap())->toBeNull();
            expect($computation->result()->isSuccess())->toBeTrue();
        });

        it('handles failure Results', function () {
            $error = new Exception('test error');
            $result = Result::failure($error);
            $computation = Computation::wrap($result, [new UnitTagA('error-context')]);
            
            expect($computation->result()->isFailure())->toBeTrue();
            expect($computation->result()->error())->toBe($error);
            expect($computation->has(UnitTagA::class))->toBeTrue();
        });

        it('preserves tags through failure transitions', function () {
            $computation = Computation::wrap('success', [new UnitTagA('context')])
                ->withResult(Result::failure(new Exception('now failed')));
            
            expect($computation->result()->isFailure())->toBeTrue();
            expect($computation->has(UnitTagA::class))->toBeTrue();
            expect($computation->first(UnitTagA::class)->value)->toBe('context');
        });
    });
});