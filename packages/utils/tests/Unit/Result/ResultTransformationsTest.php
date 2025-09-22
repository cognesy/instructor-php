<?php

use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;
use Cognesy\Utils\Result\Failure;

describe('Result transformations', function () {
    describe('map()', function () {
        test('transforms Success value', function () {
            $result = Result::success(5);
            $mapped = $result->map(fn($x) => $x * 2);

            expect($mapped)->toBeInstanceOf(Success::class);
            expect($mapped->unwrap())->toBe(10);
        });

        test('passes through Failure unchanged', function () {
            $result = Result::failure('error');
            $mapped = $result->map(fn($x) => $x * 2);

            expect($mapped)->toBeInstanceOf(Failure::class);
            expect($mapped->error())->toBe('error');
        });

        test('handles transformation that returns Result', function () {
            $result = Result::success(5);
            $mapped = $result->map(fn($x) => Result::success($x * 2));

            expect($mapped)->toBeInstanceOf(Success::class);
            expect($mapped->unwrap())->toBe(10);
        });

        test('handles transformation that returns null', function () {
            $result = Result::success(5);
            $mapped = $result->map(fn($x) => null);

            expect($mapped)->toBeInstanceOf(Success::class);
            expect($mapped->unwrap())->toBeNull();
        });

        test('catches exceptions in transformation', function () {
            $result = Result::success(5);
            $mapped = $result->map(fn($x) => throw new RuntimeException('transform failed'));

            expect($mapped)->toBeInstanceOf(Failure::class);
            expect($mapped->error())->toBeInstanceOf(RuntimeException::class);
            expect($mapped->error()->getMessage())->toBe('transform failed');
        });
    });

    describe('then()', function () {
        test('chains Success value through function returning value', function () {
            $result = Result::success(5);
            $chained = $result->then(fn($x) => $x * 2);

            expect($chained)->toBeInstanceOf(Success::class);
            expect($chained->unwrap())->toBe(10);
        });

        test('chains Success value through function returning Result', function () {
            $result = Result::success(5);
            $chained = $result->then(fn($x) => Result::success($x * 2));

            expect($chained)->toBeInstanceOf(Success::class);
            expect($chained->unwrap())->toBe(10);
        });

        test('passes through Failure unchanged', function () {
            $result = Result::failure('error');
            $chained = $result->then(fn($x) => $x * 2);

            expect($chained)->toBeInstanceOf(Failure::class);
            expect($chained->error())->toBe('error');
        });

        test('handles chaining that returns Failure', function () {
            $result = Result::success(5);
            $chained = $result->then(fn($x) => Result::failure('chain failed'));

            expect($chained)->toBeInstanceOf(Failure::class);
            expect($chained->error())->toBe('chain failed');
        });
    });

    describe('ensure()', function () {
        test('passes Success value when predicate is true', function () {
            $result = Result::success(10);
            $ensured = $result->ensure(fn($x) => $x > 5, fn($x) => "value $x is too small");

            expect($ensured)->toBeInstanceOf(Success::class);
            expect($ensured->unwrap())->toBe(10);
        });

        test('converts to Failure when predicate is false', function () {
            $result = Result::success(3);
            $ensured = $result->ensure(fn($x) => $x > 5, fn($x) => "value $x is too small");

            expect($ensured)->toBeInstanceOf(Failure::class);
            expect($ensured->error())->toBe('value 3 is too small');
        });

        test('passes through existing Failure', function () {
            $result = Result::failure('original error');
            $ensured = $result->ensure(fn($x) => true, fn($x) => 'never called');

            expect($ensured)->toBeInstanceOf(Failure::class);
            expect($ensured->error())->toBe('original error');
        });

        test('handles onFailure returning Result', function () {
            $result = Result::success(3);
            $ensured = $result->ensure(
                fn($x) => $x > 5,
                fn($x) => Result::failure("custom failure for $x")
            );

            expect($ensured)->toBeInstanceOf(Failure::class);
            expect($ensured->error())->toBe('custom failure for 3');
        });

        test('catches exceptions in predicate', function () {
            $result = Result::success(5);
            $ensured = $result->ensure(
                fn($x) => throw new RuntimeException('predicate failed'),
                fn($x) => 'never called'
            );

            expect($ensured)->toBeInstanceOf(Failure::class);
            expect($ensured->error())->toBeInstanceOf(RuntimeException::class);
        });

        test('catches exceptions in onFailure', function () {
            $result = Result::success(3);
            $ensured = $result->ensure(
                fn($x) => false,
                fn($x) => throw new RuntimeException('onFailure failed')
            );

            expect($ensured)->toBeInstanceOf(Failure::class);
            expect($ensured->error())->toBeInstanceOf(RuntimeException::class);
        });
    });

    describe('tap()', function () {
        test('executes side effect on Success and returns original', function () {
            $sideEffect = null;
            $result = Result::success('test');
            $tapped = $result->tap(function($value) use (&$sideEffect) {
                $sideEffect = $value . ' processed';
            });

            expect($tapped)->toBe($result);
            expect($sideEffect)->toBe('test processed');
        });

        test('skips side effect on Failure', function () {
            $sideEffect = 'unchanged';
            $result = Result::failure('error');
            $tapped = $result->tap(function($value) use (&$sideEffect) {
                $sideEffect = 'changed';
            });

            expect($tapped)->toBe($result);
            expect($sideEffect)->toBe('unchanged');
        });

        test('converts to Failure when side effect throws', function () {
            $result = Result::success('test');
            $tapped = $result->tap(fn($value) => throw new RuntimeException('tap failed'));

            expect($tapped)->toBeInstanceOf(Failure::class);
            expect($tapped->error())->toBeInstanceOf(RuntimeException::class);
            expect($tapped->error()->getMessage())->toBe('tap failed');
        });
    });

    describe('recover()', function () {
        test('passes Success through unchanged', function () {
            $result = Result::success('value');
            $recovered = $result->recover(fn($error) => 'recovered');

            expect($recovered)->toBe($result);
            expect($recovered->unwrap())->toBe('value');
        });

        test('recovers Failure to Success', function () {
            $result = Result::failure('error');
            $recovered = $result->recover(fn($error) => "recovered from $error");

            expect($recovered)->toBeInstanceOf(Success::class);
            expect($recovered->unwrap())->toBe('recovered from error');
        });

        test('handles recovery that throws exception', function () {
            $result = Result::failure('error');
            $recovered = $result->recover(fn($error) => throw new RuntimeException('recovery failed'));

            expect($recovered)->toBeInstanceOf(Failure::class);
            expect($recovered->error())->toBeInstanceOf(RuntimeException::class);
            expect($recovered->error()->getMessage())->toBe('recovery failed');
        });
    });

    describe('mapError()', function () {
        test('passes Success through unchanged', function () {
            $result = Result::success('value');
            $mapped = $result->mapError(fn($error) => strtoupper($error));

            expect($mapped)->toBe($result);
            expect($mapped->unwrap())->toBe('value');
        });

        test('transforms Failure error', function () {
            $result = Result::failure('error');
            $mapped = $result->mapError(fn($error) => strtoupper($error));

            expect($mapped)->toBeInstanceOf(Failure::class);
            expect($mapped->error())->toBe('ERROR');
        });

        test('handles transformation returning Result', function () {
            $result = Result::failure('error');
            $mapped = $result->mapError(fn($error) => Result::success('recovered'));

            expect($mapped)->toBeInstanceOf(Success::class);
            expect($mapped->unwrap())->toBe('recovered');
        });

        test('catches exceptions in error transformation', function () {
            $result = Result::failure('error');
            $mapped = $result->mapError(fn($error) => throw new RuntimeException('map failed'));

            expect($mapped)->toBeInstanceOf(Failure::class);
            expect($mapped->error())->toBeInstanceOf(RuntimeException::class);
            expect($mapped->error()->getMessage())->toBe('map failed');
        });
    });
});