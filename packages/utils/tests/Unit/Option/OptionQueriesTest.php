<?php declare(strict_types=1);

use Cognesy\Utils\Option\Option;

describe('Option queries', function () {
    describe('isSome() and isNone()', function () {
        it('correctly identifies Some', function () {
            $some = Option::some('test');
            expect($some->isSome())->toBeTrue();
            expect($some->isNone())->toBeFalse();
        });

        it('correctly identifies None', function () {
            $none = Option::none();
            expect($none->isNone())->toBeTrue();
            expect($none->isSome())->toBeFalse();
        });

        it('correctly identifies Some with null value', function () {
            $some = Option::some(null);
            expect($some->isSome())->toBeTrue();
            expect($some->isNone())->toBeFalse();
        });
    });

    describe('exists()', function () {
        it('returns true when Some and predicate matches', function () {
            $option = Option::some(10);
            expect($option->exists(fn($x) => $x > 5))->toBeTrue();
        });

        it('returns false when Some and predicate does not match', function () {
            $option = Option::some(3);
            expect($option->exists(fn($x) => $x > 5))->toBeFalse();
        });

        it('returns false when None regardless of predicate', function () {
            $option = Option::none();
            expect($option->exists(fn($x) => true))->toBeFalse();
            expect($option->exists(fn($x) => false))->toBeFalse();
        });

        it('works with string predicates', function () {
            $option = Option::some('hello');
            expect($option->exists(fn($x) => str_contains($x, 'ell')))->toBeTrue();
            expect($option->exists(fn($x) => str_contains($x, 'xyz')))->toBeFalse();
        });

        it('works with complex predicates', function () {
            $option = Option::some(['name' => 'John', 'age' => 25]);
            expect($option->exists(fn($x) => $x['age'] >= 18))->toBeTrue();
            expect($option->exists(fn($x) => $x['age'] < 18))->toBeFalse();
        });
    });

    describe('forAll()', function () {
        it('returns true when Some and predicate matches', function () {
            $option = Option::some(10);
            expect($option->forAll(fn($x) => $x > 5))->toBeTrue();
        });

        it('returns false when Some and predicate does not match', function () {
            $option = Option::some(3);
            expect($option->forAll(fn($x) => $x > 5))->toBeFalse();
        });

        it('returns true when None regardless of predicate', function () {
            $option = Option::none();
            expect($option->forAll(fn($x) => true))->toBeTrue();
            expect($option->forAll(fn($x) => false))->toBeTrue();
        });

        it('works with string predicates', function () {
            $option = Option::some('hello');
            expect($option->forAll(fn($x) => strlen($x) > 3))->toBeTrue();
            expect($option->forAll(fn($x) => strlen($x) > 10))->toBeFalse();
        });
    });
});