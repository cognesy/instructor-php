<?php

use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\Result\Failure;

describe('Result::success()', function () {
    test('creates Success instance with value', function () {
        $result = Result::success('test');

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBe('test');
    });

    test('creates Success instance with null value', function () {
        $result = Result::success(null);

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBeNull();
    });

    test('creates Success instance with object', function () {
        $obj = new stdClass();
        $result = Result::success($obj);

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBe($obj);
    });
});

describe('Result::failure()', function () {
    test('creates Failure instance with string error', function () {
        $result = Result::failure('error message');

        expect($result)->toBeInstanceOf(Failure::class);
        expect($result->error())->toBe('error message');
    });

    test('creates Failure instance with exception', function () {
        $exception = new RuntimeException('test error');
        $result = Result::failure($exception);

        expect($result)->toBeInstanceOf(Failure::class);
        expect($result->error())->toBe($exception);
    });

    test('creates Failure instance with array', function () {
        $errors = ['error1', 'error2'];
        $result = Result::failure($errors);

        expect($result)->toBeInstanceOf(Failure::class);
        expect($result->error())->toBe($errors);
    });
});

describe('Result::from()', function () {
    test('creates Success from non-Result value', function () {
        $result = Result::from('test');

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBe('test');
    });

    test('returns existing Result unchanged', function () {
        $original = Result::success('test');
        $result = Result::from($original);

        expect($result)->toBe($original);
    });

    test('creates Failure from Throwable', function () {
        $exception = new RuntimeException('error');
        $result = Result::from($exception);

        expect($result)->toBeInstanceOf(Failure::class);
        expect($result->error())->toBe($exception);
    });
});

describe('Result::try()', function () {
    test('creates Success when callable succeeds', function () {
        $result = Result::try(fn() => 'success');

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBe('success');
    });

    test('creates Failure when callable throws', function () {
        $result = Result::try(fn() => throw new RuntimeException('failed'));

        expect($result)->toBeInstanceOf(Failure::class);
        expect($result->error())->toBeInstanceOf(RuntimeException::class);
        expect($result->error()->getMessage())->toBe('failed');
    });

    test('handles callable with return type', function () {
        $result = Result::try(fn(): int => 42);

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBe(42);
    });
});

describe('Result::tryAll()', function () {
    test('returns Success with all results when all succeed', function () {
        $result = Result::tryAll(['input'],
            fn($x) => strtoupper($x),
            fn($x) => $x . '!'
        );

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBe(['INPUT', 'input!']);
    });

    test('returns Failure with CompositeException when any fail', function () {
        $result = Result::tryAll(['input'],
            fn($x) => strtoupper($x),
            fn($x) => throw new RuntimeException('failed')
        );

        expect($result)->toBeInstanceOf(Failure::class);
        expect($result->error())->toBeInstanceOf(\Cognesy\Utils\Exceptions\CompositeException::class);
    });

    test('returns Success with null when no callbacks provided', function () {
        $result = Result::tryAll(['input']);

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBeNull();
    });
});

describe('Result::tryUntil()', function () {
    test('returns Success with first matching result', function () {
        $result = Result::tryUntil(
            fn($x) => $x === 'target',
            [],
            fn() => 'miss',
            fn() => 'target',
            fn() => 'after'
        );

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBe('target');
    });

    test('returns Failure when all callbacks fail', function () {
        $result = Result::tryUntil(
            fn($x) => $x === 'target',
            [],
            fn() => throw new RuntimeException('fail1'),
            fn() => throw new RuntimeException('fail2')
        );

        expect($result)->toBeInstanceOf(Failure::class);
        expect($result->error())->toBeInstanceOf(\Cognesy\Utils\Exceptions\CompositeException::class);
    });

    test('returns Success false when no condition met and no errors', function () {
        $result = Result::tryUntil(
            fn($x) => $x === 'target',
            [],
            fn() => 'miss1',
            fn() => 'miss2'
        );

        expect($result)->toBeInstanceOf(Success::class);
        expect($result->unwrap())->toBeFalse();
    });
});