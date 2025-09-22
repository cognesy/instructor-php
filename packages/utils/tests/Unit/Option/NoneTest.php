<?php declare(strict_types=1);

use Cognesy\Utils\Option\Option;
use Cognesy\Utils\Option\None;

describe('None class', function () {
    describe('construction', function () {
        it('can be constructed without arguments', function () {
            $none = new None();
            expect($none)->toBeInstanceOf(None::class);
        });
    });

    describe('value access', function () {
        it('returns null via toNullable', function () {
            $none = new None();
            expect($none->toNullable())->toBeNull();
        });
    });

    describe('type identification', function () {
        it('correctly identifies as None', function () {
            $none = new None();
            expect($none->isNone())->toBeTrue();
            expect($none->isSome())->toBeFalse();
        });
    });

    describe('singleton behavior', function () {
        it('different instances are separate objects', function () {
            $none1 = new None();
            $none2 = new None();
            // They are different instances (no singleton pattern)
            expect($none1 !== $none2)->toBeTrue();
            // But they behave identically
            expect($none1->isNone())->toBe($none2->isNone());
            expect($none1->toNullable())->toBe($none2->toNullable());
        });
    });

    describe('integration with Option', function () {
        it('works with Option::none factory', function () {
            $none1 = new None();
            $none2 = Option::none();

            expect($none1)->toBeInstanceOf(None::class);
            expect($none2)->toBeInstanceOf(None::class);
            expect($none1->toNullable())->toBe($none2->toNullable());
        });

        it('works in Option transformations', function () {
            $none = new None();

            // Transformations should preserve None
            $mapped = $none->map(fn($x) => $x * 2);
            $filtered = $none->filter(fn($x) => true);
            $flatMapped = $none->flatMap(fn($x) => Option::some($x));

            expect($mapped)->toBeInstanceOf(None::class);
            expect($filtered)->toBeInstanceOf(None::class);
            expect($flatMapped)->toBeInstanceOf(None::class);
        });
    });

    describe('behavior consistency', function () {
        it('consistently returns None for transformations', function () {
            $none = new None();

            expect($none->map(fn($x) => 'anything'))->toBeInstanceOf(None::class);
            expect($none->flatMap(fn($x) => Option::some('anything')))->toBeInstanceOf(None::class);
            expect($none->filter(fn($x) => true))->toBeInstanceOf(None::class);
        });

        it('consistently returns false for exists', function () {
            $none = new None();

            expect($none->exists(fn($x) => true))->toBeFalse();
            expect($none->exists(fn($x) => false))->toBeFalse();
        });

        it('consistently returns true for forAll', function () {
            $none = new None();

            expect($none->forAll(fn($x) => true))->toBeTrue();
            expect($none->forAll(fn($x) => false))->toBeTrue();
        });

        it('does not execute callbacks in ifSome', function () {
            $executed = false;
            $none = new None();

            $result = $none->ifSome(function () use (&$executed) {
                $executed = true;
            });

            expect($executed)->toBeFalse();
            expect($result)->toBe($none);
        });

        it('executes callbacks in ifNone', function () {
            $executed = false;
            $none = new None();

            $result = $none->ifNone(function () use (&$executed) {
                $executed = true;
            });

            expect($executed)->toBeTrue();
            expect($result)->toBe($none);
        });
    });

    describe('destructuring behavior', function () {
        it('uses onNone branch in match', function () {
            $none = new None();
            $result = $none->match(
                fn() => 'none branch',
                fn($x) => 'some branch'
            );
            expect($result)->toBe('none branch');
        });

        it('returns default in getOrElse', function () {
            $none = new None();
            expect($none->getOrElse('default'))->toBe('default');
            expect($none->getOrElse(fn() => 'computed'))->toBe('computed');
        });

        it('returns alternative in orElse', function () {
            $none = new None();
            $alternative = Option::some('alternative');
            expect($none->orElse($alternative))->toBe($alternative);
        });
    });

    describe('immutability', function () {
        it('is readonly and immutable', function () {
            $none = new None();
            // None has no internal state to mutate, but we verify it stays None
            expect($none->isNone())->toBeTrue();

            // Operations return new instances, don't mutate original
            $mapped = $none->map(fn($x) => 'something');
            expect($none->isNone())->toBeTrue();
            expect($mapped)->toBeInstanceOf(None::class);
        });
    });
});