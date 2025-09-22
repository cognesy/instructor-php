<?php declare(strict_types=1);

use Cognesy\Utils\Option\Option;
use Cognesy\Utils\Option\Some;

describe('Some class', function () {
    describe('construction', function () {
        it('can be constructed with any value', function () {
            $some = new Some('test');
            expect($some)->toBeInstanceOf(Some::class);
        });

        it('can be constructed with null', function () {
            $some = new Some(null);
            expect($some)->toBeInstanceOf(Some::class);
            expect($some->toNullable())->toBeNull();
        });

        it('can be constructed with complex values', function () {
            $value = ['key' => 'value', 'nested' => ['data' => 123]];
            $some = new Some($value);
            expect($some->toNullable())->toBe($value);
        });

        it('can be constructed with objects', function () {
            $obj = new stdClass();
            $obj->prop = 'value';
            $some = new Some($obj);
            expect($some->toNullable())->toBe($obj);
        });
    });

    describe('value access', function () {
        it('returns stored value via toNullable', function () {
            $some = new Some('test value');
            expect($some->toNullable())->toBe('test value');
        });

        it('preserves value types', function () {
            $intSome = new Some(42);
            $floatSome = new Some(3.14);
            $boolSome = new Some(true);
            $arraySome = new Some([1, 2, 3]);

            expect($intSome->toNullable())->toBe(42);
            expect($floatSome->toNullable())->toBe(3.14);
            expect($boolSome->toNullable())->toBeTrue();
            expect($arraySome->toNullable())->toBe([1, 2, 3]);
        });
    });

    describe('type identification', function () {
        it('correctly identifies as Some', function () {
            $some = new Some('test');
            expect($some->isSome())->toBeTrue();
            expect($some->isNone())->toBeFalse();
        });
    });

    describe('immutability', function () {
        it('is readonly and immutable', function () {
            $some = new Some(['mutable' => 'value']);
            $originalValue = $some->toNullable();

            // The Some itself is immutable, but this tests that we get the same reference
            expect($some->toNullable())->toBe($originalValue);
        });
    });

    describe('edge cases', function () {
        it('handles zero values', function () {
            $zeroSome = new Some(0);
            expect($zeroSome->isSome())->toBeTrue();
            expect($zeroSome->toNullable())->toBe(0);
        });

        it('handles empty string', function () {
            $emptySome = new Some('');
            expect($emptySome->isSome())->toBeTrue();
            expect($emptySome->toNullable())->toBe('');
        });

        it('handles false value', function () {
            $falseSome = new Some(false);
            expect($falseSome->isSome())->toBeTrue();
            expect($falseSome->toNullable())->toBeFalse();
        });

        it('handles empty array', function () {
            $emptySome = new Some([]);
            expect($emptySome->isSome())->toBeTrue();
            expect($emptySome->toNullable())->toBe([]);
        });
    });

    describe('integration with Option', function () {
        it('works with Option::some factory', function () {
            $some1 = new Some('test');
            $some2 = Option::some('test');

            expect($some1)->toBeInstanceOf(Some::class);
            expect($some2)->toBeInstanceOf(Some::class);
            expect($some1->toNullable())->toBe($some2->toNullable());
        });

        it('works in Option transformations', function () {
            $some = new Some(5);
            $result = $some->map(fn($x) => $x * 2);

            expect($result)->toBeInstanceOf(Some::class);
            expect($result->toNullable())->toBe(10);
        });
    });
});