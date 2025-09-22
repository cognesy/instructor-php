<?php declare(strict_types=1);

use Cognesy\Utils\Option\Option;
use Cognesy\Utils\Result\Result;

describe('Option destructuring', function () {
    describe('match()', function () {
        it('calls onSome when Some', function () {
            $option = Option::some('test');
            $result = $option->match(
                fn() => 'was none',
                fn($value) => "was some: $value"
            );
            expect($result)->toBe('was some: test');
        });

        it('calls onNone when None', function () {
            $option = Option::none();
            $result = $option->match(
                fn() => 'was none',
                fn($value) => "was some: $value"
            );
            expect($result)->toBe('was none');
        });

        it('works with different return types', function () {
            $someOption = Option::some(5);
            $noneOption = Option::none();

            $someResult = $someOption->match(
                fn() => 0,
                fn($value) => $value * 2
            );

            $noneResult = $noneOption->match(
                fn() => 0,
                fn($value) => $value * 2
            );

            expect($someResult)->toBe(10);
            expect($noneResult)->toBe(0);
        });

        it('handles complex pattern matching', function () {
            $option = Option::some(['type' => 'user', 'name' => 'John']);
            $result = $option->match(
                fn() => 'No data',
                fn($data) => match($data['type']) {
                    'user' => "Hello, {$data['name']}",
                    'admin' => "Admin: {$data['name']}",
                    default => "Unknown: {$data['name']}"
                }
            );
            expect($result)->toBe('Hello, John');
        });
    });

    describe('getOrElse()', function () {
        it('returns value when Some', function () {
            $option = Option::some('test');
            expect($option->getOrElse('default'))->toBe('test');
        });

        it('returns default when None', function () {
            $option = Option::none();
            expect($option->getOrElse('default'))->toBe('default');
        });

        it('works with callable default', function () {
            $option = Option::none();
            expect($option->getOrElse(fn() => 'computed default'))->toBe('computed default');
        });

        it('does not call callable default when Some', function () {
            $called = false;
            $option = Option::some('test');
            $result = $option->getOrElse(function () use (&$called) {
                $called = true;
                return 'default';
            });

            expect($result)->toBe('test');
            expect($called)->toBeFalse();
        });

        it('works with complex default values', function () {
            $option = Option::none();
            $default = ['status' => 'not_found', 'data' => null];
            expect($option->getOrElse($default))->toBe($default);
        });
    });

    describe('orElse()', function () {
        it('returns self when Some', function () {
            $option = Option::some('test');
            $alternative = Option::some('alternative');
            $result = $option->orElse($alternative);
            expect($result)->toBe($option);
            expect($result->toNullable())->toBe('test');
        });

        it('returns alternative when None', function () {
            $option = Option::none();
            $alternative = Option::some('alternative');
            $result = $option->orElse($alternative);
            expect($result->toNullable())->toBe('alternative');
        });

        it('works with callable alternative', function () {
            $option = Option::none();
            $result = $option->orElse(fn() => Option::some('computed'));
            expect($result->toNullable())->toBe('computed');
        });

        it('does not call callable alternative when Some', function () {
            $called = false;
            $option = Option::some('test');
            $result = $option->orElse(function () use (&$called) {
                $called = true;
                return Option::some('alternative');
            });

            expect($result->toNullable())->toBe('test');
            expect($called)->toBeFalse();
        });

        it('chains multiple orElse calls', function () {
            $result = Option::none()
                ->orElse(Option::none())
                ->orElse(Option::some('found'));
            expect($result->toNullable())->toBe('found');
        });
    });

    describe('toNullable()', function () {
        it('returns value when Some', function () {
            $option = Option::some('test');
            expect($option->toNullable())->toBe('test');
        });

        it('returns null when None', function () {
            $option = Option::none();
            expect($option->toNullable())->toBeNull();
        });

        it('returns null when Some contains null', function () {
            $option = Option::some(null);
            expect($option->toNullable())->toBeNull();
        });

        it('works with complex values', function () {
            $value = ['nested' => ['data' => 'test']];
            $option = Option::some($value);
            expect($option->toNullable())->toBe($value);
        });
    });

    describe('toResult()', function () {
        it('returns success Result when Some', function () {
            $option = Option::some('test');
            $result = $option->toResult(new Exception('error'));
            expect($result->isSuccess())->toBeTrue();
            expect($result->valueOr(null))->toBe('test');
        });

        it('returns failure Result when None', function () {
            $option = Option::none();
            $error = new Exception('not found');
            $result = $option->toResult($error);
            expect($result->isFailure())->toBeTrue();
            expect($result->error())->toBe($error);
        });

        it('works with callable error factory', function () {
            $option = Option::none();
            $result = $option->toResult(fn() => new Exception('computed error'));
            expect($result->isFailure())->toBeTrue();
            expect($result->error()->getMessage())->toBe('computed error');
        });

        it('does not call error factory when Some', function () {
            $called = false;
            $option = Option::some('test');
            $result = $option->toResult(function () use (&$called) {
                $called = true;
                return new Exception('error');
            });

            expect($result->isSuccess())->toBeTrue();
            expect($called)->toBeFalse();
        });
    });

    describe('toSuccessOr()', function () {
        it('returns success Result with value when Some', function () {
            $option = Option::some('test');
            $result = $option->toSuccessOr('default');
            expect($result->isSuccess())->toBeTrue();
            expect($result->valueOr(null))->toBe('test');
        });

        it('returns success Result with default when None', function () {
            $option = Option::none();
            $result = $option->toSuccessOr('default');
            expect($result->isSuccess())->toBeTrue();
            expect($result->valueOr(null))->toBe('default');
        });

        it('works with callable default', function () {
            $option = Option::none();
            $result = $option->toSuccessOr(fn() => 'computed');
            expect($result->isSuccess())->toBeTrue();
            expect($result->valueOr(null))->toBe('computed');
        });

        it('does not call default when Some', function () {
            $called = false;
            $option = Option::some('test');
            $result = $option->toSuccessOr(function () use (&$called) {
                $called = true;
                return 'default';
            });

            expect($result->valueOr(null))->toBe('test');
            expect($called)->toBeFalse();
        });
    });
});