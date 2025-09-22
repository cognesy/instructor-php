<?php

use Cognesy\Utils\Result\Result;

describe('Result state inspection', function () {
    describe('isSuccess() and isFailure()', function () {
        test('Success returns correct state', function () {
            $result = Result::success('value');

            expect($result->isSuccess())->toBeTrue();
            expect($result->isFailure())->toBeFalse();
        });

        test('Failure returns correct state', function () {
            $result = Result::failure('error');

            expect($result->isSuccess())->toBeFalse();
            expect($result->isFailure())->toBeTrue();
        });
    });

    describe('isSuccessAndNull()', function () {
        test('returns true for Success with null value', function () {
            $result = Result::success(null);

            expect($result->isSuccessAndNull())->toBeTrue();
        });

        test('returns false for Success with non-null value', function () {
            $result = Result::success('value');

            expect($result->isSuccessAndNull())->toBeFalse();
        });

        test('returns false for Failure', function () {
            $result = Result::failure('error');

            expect($result->isSuccessAndNull())->toBeFalse();
        });
    });

    describe('isSuccessAndTrue()', function () {
        test('returns true for Success with true value', function () {
            $result = Result::success(true);

            expect($result->isSuccessAndTrue())->toBeTrue();
        });

        test('returns false for Success with non-true value', function () {
            $result = Result::success(false);

            expect($result->isSuccessAndTrue())->toBeFalse();
        });

        test('returns false for Success with truthy value', function () {
            $result = Result::success('truthy');

            expect($result->isSuccessAndTrue())->toBeFalse();
        });

        test('returns false for Failure', function () {
            $result = Result::failure('error');

            expect($result->isSuccessAndTrue())->toBeFalse();
        });
    });

    describe('isSuccessAndFalse()', function () {
        test('returns true for Success with false value', function () {
            $result = Result::success(false);

            expect($result->isSuccessAndFalse())->toBeTrue();
        });

        test('returns false for Success with non-false value', function () {
            $result = Result::success(true);

            expect($result->isSuccessAndFalse())->toBeFalse();
        });

        test('returns false for Success with falsy value', function () {
            $result = Result::success('');

            expect($result->isSuccessAndFalse())->toBeFalse();
        });

        test('returns false for Failure', function () {
            $result = Result::failure('error');

            expect($result->isSuccessAndFalse())->toBeFalse();
        });
    });

    describe('isType()', function () {
        test('returns true for matching type in Success', function () {
            expect(Result::success(42)->isType('integer'))->toBeTrue();
            expect(Result::success('text')->isType('string'))->toBeTrue();
            expect(Result::success([])->isType('array'))->toBeTrue();
            expect(Result::success(3.14)->isType('double'))->toBeTrue();
        });

        test('returns false for non-matching type in Success', function () {
            expect(Result::success(42)->isType('string'))->toBeFalse();
            expect(Result::success('text')->isType('integer'))->toBeFalse();
        });

        test('returns false for Failure', function () {
            $result = Result::failure('error');

            expect($result->isType('string'))->toBeFalse();
        });
    });

    describe('isInstanceOf()', function () {
        test('returns true for matching instance in Success', function () {
            $obj = new stdClass();
            $result = Result::success($obj);

            expect($result->isInstanceOf(stdClass::class))->toBeTrue();
        });

        test('returns false for non-matching instance in Success', function () {
            $obj = new stdClass();
            $result = Result::success($obj);

            expect($result->isInstanceOf(DateTime::class))->toBeFalse();
        });

        test('returns false for non-object value in Success', function () {
            $result = Result::success('string');

            expect($result->isInstanceOf(stdClass::class))->toBeFalse();
        });

        test('returns false for Failure', function () {
            $result = Result::failure('error');

            expect($result->isInstanceOf(stdClass::class))->toBeFalse();
        });
    });

    describe('matches()', function () {
        test('returns true when predicate matches Success value', function () {
            $result = Result::success(10);

            expect($result->matches(fn($x) => $x > 5))->toBeTrue();
            expect($result->matches(fn($x) => $x === 10))->toBeTrue();
        });

        test('returns false when predicate does not match Success value', function () {
            $result = Result::success(3);

            expect($result->matches(fn($x) => $x > 5))->toBeFalse();
        });

        test('returns false for Failure', function () {
            $result = Result::failure('error');

            expect($result->matches(fn($x) => true))->toBeFalse();
        });

        test('handles complex predicates', function () {
            $result = Result::success(['a', 'b', 'c']);

            expect($result->matches(fn($arr) => count($arr) === 3))->toBeTrue();
            expect($result->matches(fn($arr) => in_array('b', $arr)))->toBeTrue();
            expect($result->matches(fn($arr) => count($arr) > 5))->toBeFalse();
        });
    });

    describe('valueOr()', function () {
        test('returns value for Success', function () {
            $result = Result::success('actual');

            expect($result->valueOr('default'))->toBe('actual');
        });

        test('returns default for Failure', function () {
            $result = Result::failure('error');

            expect($result->valueOr('default'))->toBe('default');
        });

        test('returns null value for Success with null', function () {
            $result = Result::success(null);

            expect($result->valueOr('default'))->toBeNull();
        });
    });

    describe('exceptionOr()', function () {
        test('returns default for Success', function () {
            $result = Result::success('value');

            expect($result->exceptionOr('default'))->toBe('default');
        });

        test('returns exception for Failure with throwable error', function () {
            $exception = new RuntimeException('test');
            $result = Result::failure($exception);

            expect($result->exceptionOr('default'))->toBe($exception);
        });

        test('returns converted exception for Failure with string error', function () {
            $result = Result::failure('error message');

            $exception = $result->exceptionOr('default');

            expect($exception)->toBeInstanceOf(RuntimeException::class);
            expect($exception->getMessage())->toBe('error message');
        });
    });
});