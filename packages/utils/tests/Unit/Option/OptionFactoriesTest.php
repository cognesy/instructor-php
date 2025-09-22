<?php declare(strict_types=1);

use Cognesy\Utils\Option\Option;
use Cognesy\Utils\Option\Some;
use Cognesy\Utils\Option\None;
use Cognesy\Utils\Result\Result;

describe('Option factories', function () {
    describe('some()', function () {
        it('creates Some with value', function () {
            $option = Option::some('test');
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->isSome())->toBeTrue();
            expect($option->toNullable())->toBe('test');
        });

        it('creates Some with null value', function () {
            $option = Option::some(null);
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->isSome())->toBeTrue();
            expect($option->toNullable())->toBeNull();
        });

        it('creates Some with complex value', function () {
            $value = ['key' => 'value'];
            $option = Option::some($value);
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->toNullable())->toBe($value);
        });
    });

    describe('none()', function () {
        it('creates None', function () {
            $option = Option::none();
            expect($option)->toBeInstanceOf(None::class);
            expect($option->isNone())->toBeTrue();
            expect($option->toNullable())->toBeNull();
        });
    });

    describe('fromNullable()', function () {
        it('creates Some from non-null value', function () {
            $option = Option::fromNullable('test');
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->toNullable())->toBe('test');
        });

        it('creates None from null value', function () {
            $option = Option::fromNullable(null);
            expect($option)->toBeInstanceOf(None::class);
            expect($option->toNullable())->toBeNull();
        });

        it('creates Some from zero', function () {
            $option = Option::fromNullable(0);
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->toNullable())->toBe(0);
        });

        it('creates Some from empty string', function () {
            $option = Option::fromNullable('');
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->toNullable())->toBe('');
        });

        it('creates Some from false', function () {
            $option = Option::fromNullable(false);
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->toNullable())->toBeFalse();
        });
    });

    describe('fromResult()', function () {
        it('creates Some from success Result with value', function () {
            $result = Result::success('test');
            $option = Option::fromResult($result);
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->toNullable())->toBe('test');
        });

        it('creates None from failure Result', function () {
            $result = Result::failure(new Exception('error'));
            $option = Option::fromResult($result);
            expect($option)->toBeInstanceOf(None::class);
        });

        it('creates None from success Result with null when noneOnNull=true', function () {
            $result = Result::success(null);
            $option = Option::fromResult($result, true);
            expect($option)->toBeInstanceOf(None::class);
        });

        it('creates Some from success Result with null when noneOnNull=false', function () {
            $result = Result::success(null);
            $option = Option::fromResult($result, false);
            expect($option)->toBeInstanceOf(Some::class);
            expect($option->toNullable())->toBeNull();
        });
    });
});