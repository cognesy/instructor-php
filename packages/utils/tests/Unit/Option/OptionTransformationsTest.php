<?php declare(strict_types=1);

use Cognesy\Utils\Option\Option;

describe('Option transformations', function () {
    describe('map()', function () {
        it('transforms Some value', function () {
            $option = Option::some(5);
            $result = $option->map(fn($x) => $x * 2);
            expect($result->isSome())->toBeTrue();
            expect($result->toNullable())->toBe(10);
        });

        it('returns None when applied to None', function () {
            $option = Option::none();
            $result = $option->map(fn($x) => $x * 2);
            expect($result->isNone())->toBeTrue();
        });

        it('chains multiple map operations', function () {
            $option = Option::some(5);
            $result = $option
                ->map(fn($x) => $x * 2)
                ->map(fn($x) => $x + 1)
                ->map(fn($x) => (string) $x);
            expect($result->toNullable())->toBe('11');
        });

        it('works with complex transformations', function () {
            $option = Option::some(['name' => 'John']);
            $result = $option->map(fn($x) => $x['name']);
            expect($result->toNullable())->toBe('John');
        });
    });

    describe('flatMap()', function () {
        it('flattens nested Options when Some', function () {
            $option = Option::some(5);
            $result = $option->flatMap(fn($x) => Option::some($x * 2));
            expect($result->isSome())->toBeTrue();
            expect($result->toNullable())->toBe(10);
        });

        it('returns None when applied to None', function () {
            $option = Option::none();
            $result = $option->flatMap(fn($x) => Option::some($x * 2));
            expect($result->isNone())->toBeTrue();
        });

        it('returns None when function returns None', function () {
            $option = Option::some(5);
            $result = $option->flatMap(fn($x) => Option::none());
            expect($result->isNone())->toBeTrue();
        });

        it('chains flatMap operations', function () {
            $option = Option::some(5);
            $result = $option
                ->flatMap(fn($x) => Option::some($x * 2))
                ->flatMap(fn($x) => $x > 5 ? Option::some($x) : Option::none());
            expect($result->toNullable())->toBe(10);
        });

        it('handles non-Option return values by wrapping in Some', function () {
            $option = Option::some(5);
            $result = $option->flatMap(fn($x) => $x * 2);
            expect($result->isSome())->toBeTrue();
            expect($result->toNullable())->toBe(10);
        });
    });

    describe('andThen()', function () {
        it('is alias for flatMap', function () {
            $option = Option::some(5);
            $flatMapResult = $option->flatMap(fn($x) => Option::some($x * 2));
            $andThenResult = $option->andThen(fn($x) => Option::some($x * 2));
            expect($andThenResult->toNullable())->toBe($flatMapResult->toNullable());
        });
    });

    describe('filter()', function () {
        it('keeps Some when predicate is true', function () {
            $option = Option::some(10);
            $result = $option->filter(fn($x) => $x > 5);
            expect($result->isSome())->toBeTrue();
            expect($result->toNullable())->toBe(10);
        });

        it('returns None when predicate is false', function () {
            $option = Option::some(3);
            $result = $option->filter(fn($x) => $x > 5);
            expect($result->isNone())->toBeTrue();
        });

        it('returns None when applied to None', function () {
            $option = Option::none();
            $result = $option->filter(fn($x) => true);
            expect($result->isNone())->toBeTrue();
        });

        it('chains with other operations', function () {
            $option = Option::some(5);
            $result = $option
                ->map(fn($x) => $x * 2)
                ->filter(fn($x) => $x > 8);
            expect($result->toNullable())->toBe(10);
        });
    });

    describe('zipWith()', function () {
        it('combines two Some values', function () {
            $option1 = Option::some(5);
            $option2 = Option::some(10);
            $result = $option1->zipWith($option2, fn($x, $y) => $x + $y);
            expect($result->isSome())->toBeTrue();
            expect($result->toNullable())->toBe(15);
        });

        it('returns None when first is None', function () {
            $option1 = Option::none();
            $option2 = Option::some(10);
            $result = $option1->zipWith($option2, fn($x, $y) => $x + $y);
            expect($result->isNone())->toBeTrue();
        });

        it('returns None when second is None', function () {
            $option1 = Option::some(5);
            $option2 = Option::none();
            $result = $option1->zipWith($option2, fn($x, $y) => $x + $y);
            expect($result->isNone())->toBeTrue();
        });

        it('returns None when both are None', function () {
            $option1 = Option::none();
            $option2 = Option::none();
            $result = $option1->zipWith($option2, fn($x, $y) => $x + $y);
            expect($result->isNone())->toBeTrue();
        });

        it('works with complex combinators', function () {
            $option1 = Option::some(['a' => 1]);
            $option2 = Option::some(['b' => 2]);
            $result = $option1->zipWith($option2, fn($x, $y) => array_merge($x, $y));
            expect($result->toNullable())->toBe(['a' => 1, 'b' => 2]);
        });
    });
});