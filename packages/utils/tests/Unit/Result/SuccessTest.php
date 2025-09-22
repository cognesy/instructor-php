<?php

use Cognesy\Utils\Result\Success;

describe('Success', function () {
    describe('construction', function () {
        test('constructs with value', function () {
            $success = new Success('test value');

            expect($success->unwrap())->toBe('test value');
        });

        test('constructs with null value', function () {
            $success = new Success(null);

            expect($success->unwrap())->toBeNull();
        });

        test('constructs with object value', function () {
            $obj = new stdClass();
            $success = new Success($obj);

            expect($success->unwrap())->toBe($obj);
        });

        test('constructs with array value', function () {
            $array = ['a', 'b', 'c'];
            $success = new Success($array);

            expect($success->unwrap())->toBe($array);
        });
    });

    describe('unwrap()', function () {
        test('returns the wrapped value', function () {
            $success = new Success(42);

            expect($success->unwrap())->toBe(42);
        });

        test('returns exact object reference', function () {
            $obj = new stdClass();
            $obj->property = 'test';
            $success = new Success($obj);

            $unwrapped = $success->unwrap();
            expect($unwrapped)->toBe($obj);
            expect($unwrapped->property)->toBe('test');
        });

        test('returns null when value is null', function () {
            $success = new Success(null);

            expect($success->unwrap())->toBeNull();
        });
    });

    describe('state methods', function () {
        test('isSuccess returns true', function () {
            $success = new Success('value');

            expect($success->isSuccess())->toBeTrue();
        });

        test('isFailure returns false', function () {
            $success = new Success('value');

            expect($success->isFailure())->toBeFalse();
        });
    });

    describe('type preservation', function () {
        test('preserves string type', function () {
            $success = new Success('string value');
            $unwrapped = $success->unwrap();

            expect($unwrapped)->toBeString();
            expect($unwrapped)->toBe('string value');
        });

        test('preserves integer type', function () {
            $success = new Success(123);
            $unwrapped = $success->unwrap();

            expect($unwrapped)->toBeInt();
            expect($unwrapped)->toBe(123);
        });

        test('preserves float type', function () {
            $success = new Success(3.14);
            $unwrapped = $success->unwrap();

            expect($unwrapped)->toBeFloat();
            expect($unwrapped)->toBe(3.14);
        });

        test('preserves boolean type', function () {
            $successTrue = new Success(true);
            $successFalse = new Success(false);

            expect($successTrue->unwrap())->toBeBool();
            expect($successTrue->unwrap())->toBeTrue();
            expect($successFalse->unwrap())->toBeBool();
            expect($successFalse->unwrap())->toBeFalse();
        });

        test('preserves array type', function () {
            $array = [1, 2, 3];
            $success = new Success($array);
            $unwrapped = $success->unwrap();

            expect($unwrapped)->toBeArray();
            expect($unwrapped)->toBe($array);
        });
    });

    describe('readonly behavior', function () {
        test('Success is readonly class', function () {
            $reflection = new ReflectionClass(Success::class);

            expect($reflection->isReadOnly())->toBeTrue();
        });
    });
});