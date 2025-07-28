<?php declare(strict_types=1);

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\StampInterface;
use Cognesy\Utils\Result\Result;

// Test stamps for unit testing
class UnitStampA implements StampInterface
{
    public function __construct(public readonly string $value) {}
}

class UnitStampB implements StampInterface
{
    public function __construct(public readonly int $number) {}
}

class UnitStampC implements StampInterface
{
    public function __construct(public readonly array $data) {}
}

describe('Envelope Unit Tests', function () {
    describe('Construction', function () {
        it('constructs with Result and stamps', function () {
            $result = Result::success('test data');
            $stamps = [new UnitStampA('test')];
            
            $envelope = new Envelope($result, ['UnitStampA' => $stamps]);
            
            expect($envelope->getResult())->toBe($result);
            expect($envelope->count())->toBe(1);
        });

        it('wraps mixed values with wrap()', function () {
            $envelope = Envelope::wrap('simple string');
            
            expect($envelope->getResult()->unwrap())->toBe('simple string');
            expect($envelope->getResult()->isSuccess())->toBeTrue();
        });

        it('wraps Result values directly', function () {
            $result = Result::failure(new Exception('test error'));
            $envelope = Envelope::wrap($result);
            
            expect($envelope->getResult())->toBe($result);
            expect($envelope->getResult()->isFailure())->toBeTrue();
        });

        it('wraps with initial stamps', function () {
            $stamps = [new UnitStampA('initial'), new UnitStampB(42)];
            $envelope = Envelope::wrap('data', $stamps);
            
            expect($envelope->count())->toBe(2);
            expect($envelope->has(UnitStampA::class))->toBeTrue();
            expect($envelope->has(UnitStampB::class))->toBeTrue();
        });
    });

    describe('Result Access', function () {
        it('returns the wrapped Result', function () {
            $result = Result::success(['key' => 'value']);
            $envelope = new Envelope($result);
            
            expect($envelope->getResult())->toBe($result);
            expect($envelope->getResult()->unwrap())->toBe(['key' => 'value']);
        });
    });

    describe('Stamp Management', function () {
        it('adds stamps with with()', function () {
            $envelope = Envelope::wrap('data');
            $stampA = new UnitStampA('test');
            $stampB = new UnitStampB(123);
            
            $newEnvelope = $envelope->with($stampA, $stampB);
            
            expect($newEnvelope->count())->toBe(2);
            expect($newEnvelope->has(UnitStampA::class))->toBeTrue();
            expect($newEnvelope->has(UnitStampB::class))->toBeTrue();
            
            // Original unchanged (immutability)
            expect($envelope->count())->toBe(0);
        });

        it('adds multiple stamps of same type', function () {
            $envelope = Envelope::wrap('data');
            $stamp1 = new UnitStampA('first');
            $stamp2 = new UnitStampA('second');
            
            $newEnvelope = $envelope->with($stamp1, $stamp2);
            
            expect($newEnvelope->count(UnitStampA::class))->toBe(2);
            expect($newEnvelope->first(UnitStampA::class)->value)->toBe('first');
            expect($newEnvelope->last(UnitStampA::class)->value)->toBe('second');
        });

        it('removes stamps with without()', function () {
            $envelope = Envelope::wrap('data', [
                new UnitStampA('keep'),
                new UnitStampB(123),
                new UnitStampC(['remove' => 'me'])
            ]);
            
            $filtered = $envelope->without(UnitStampB::class, UnitStampC::class);
            
            expect($filtered->count())->toBe(1);
            expect($filtered->has(UnitStampA::class))->toBeTrue();
            expect($filtered->has(UnitStampB::class))->toBeFalse();
            expect($filtered->has(UnitStampC::class))->toBeFalse();
        });

        it('replaces message with withMessage()', function () {
            $envelope = Envelope::wrap('original', [new UnitStampA('keep')]);
            $newResult = Result::success('replaced');
            
            $newEnvelope = $envelope->withMessage($newResult);
            
            expect($newEnvelope->getResult()->unwrap())->toBe('replaced');
            expect($newEnvelope->count())->toBe(1); // Stamps preserved
            expect($newEnvelope->has(UnitStampA::class))->toBeTrue();
        });
    });

    describe('Stamp Queries', function () {
        beforeEach(function () {
            $this->envelope = Envelope::wrap('data', [
                new UnitStampA('first'),
                new UnitStampB(10),
                new UnitStampA('second'),
                new UnitStampB(20),
                new UnitStampC(['test' => 'data']),
                new UnitStampA('third')
            ]);
        });

        it('returns all stamps with all()', function () {
            // Test getting all stamps by counting by type
            expect($this->envelope->count())->toBe(6);
            expect($this->envelope->count(UnitStampA::class))->toBe(3);
            expect($this->envelope->count(UnitStampB::class))->toBe(2);
            expect($this->envelope->count(UnitStampC::class))->toBe(1);
        });

        it('returns filtered stamps with all(stampClass)', function () {
            $stampAs = $this->envelope->all(UnitStampA::class);
            expect(count($stampAs))->toBe(3);
            expect($stampAs[0]->value)->toBe('first');
            expect($stampAs[2]->value)->toBe('third');
        });

        it('returns first stamp with first()', function () {
            $first = $this->envelope->first(UnitStampA::class);
            expect($first)->toBeInstanceOf(UnitStampA::class);
            expect($first->value)->toBe('first');
        });

        it('returns last stamp with last()', function () {
            $last = $this->envelope->last(UnitStampA::class);
            expect($last)->toBeInstanceOf(UnitStampA::class);
            expect($last->value)->toBe('third');
        });

        it('returns null for non-existent stamps', function () {
            $nonExistent = $this->envelope->first('NonExistentStamp');
            expect($nonExistent)->toBeNull();
            
            $nonExistent = $this->envelope->last('NonExistentStamp');
            expect($nonExistent)->toBeNull();
        });

        it('checks stamp existence with has()', function () {
            expect($this->envelope->has(UnitStampA::class))->toBeTrue();
            expect($this->envelope->has(UnitStampB::class))->toBeTrue();
            expect($this->envelope->has('NonExistentStamp'))->toBeFalse();
        });

        it('counts stamps with count()', function () {
            expect($this->envelope->count())->toBe(6);
            expect($this->envelope->count(UnitStampA::class))->toBe(3);
            expect($this->envelope->count(UnitStampB::class))->toBe(2);
            expect($this->envelope->count(UnitStampC::class))->toBe(1);
            expect($this->envelope->count('NonExistentStamp'))->toBe(0);
        });
    });

    describe('Immutability', function () {
        it('preserves original envelope when adding stamps', function () {
            $original = Envelope::wrap('data', [new UnitStampA('original')]);
            $modified = $original->with(new UnitStampB(123));
            
            expect($original->count())->toBe(1);
            expect($original->has(UnitStampB::class))->toBeFalse();
            
            expect($modified->count())->toBe(2);
            expect($modified->has(UnitStampB::class))->toBeTrue();
        });

        it('preserves original envelope when removing stamps', function () {
            $original = Envelope::wrap('data', [
                new UnitStampA('keep'),
                new UnitStampB(123)
            ]);
            $modified = $original->without(UnitStampB::class);
            
            expect($original->count())->toBe(2);
            expect($original->has(UnitStampB::class))->toBeTrue();
            
            expect($modified->count())->toBe(1);
            expect($modified->has(UnitStampB::class))->toBeFalse();
        });

        it('preserves original envelope when replacing message', function () {
            $original = Envelope::wrap('original');
            $modified = $original->withMessage(Result::success('modified'));
            
            expect($original->getResult()->unwrap())->toBe('original');
            expect($modified->getResult()->unwrap())->toBe('modified');
        });
    });

    describe('Edge Cases', function () {
        it('handles empty stamp arrays', function () {
            $envelope = Envelope::wrap('data', []);
            
            expect($envelope->count())->toBe(0);
            expect($envelope->has(UnitStampA::class))->toBeFalse();
        });

        it('handles null values in Result', function () {
            $envelope = Envelope::wrap(null);
            
            expect($envelope->getResult()->unwrap())->toBeNull();
            expect($envelope->getResult()->isSuccess())->toBeTrue();
        });

        it('handles failure Results', function () {
            $error = new Exception('test error');
            $result = Result::failure($error);
            $envelope = Envelope::wrap($result, [new UnitStampA('error-context')]);
            
            expect($envelope->getResult()->isFailure())->toBeTrue();
            expect($envelope->getResult()->error())->toBe($error);
            expect($envelope->has(UnitStampA::class))->toBeTrue();
        });

        it('preserves stamps through failure transitions', function () {
            $envelope = Envelope::wrap('success', [new UnitStampA('context')])
                ->withMessage(Result::failure(new Exception('now failed')));
            
            expect($envelope->getResult()->isFailure())->toBeTrue();
            expect($envelope->has(UnitStampA::class))->toBeTrue();
            expect($envelope->first(UnitStampA::class)->value)->toBe('context');
        });
    });
});