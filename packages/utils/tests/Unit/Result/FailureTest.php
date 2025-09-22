<?php

use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Exceptions\CompositeException;

describe('Failure', function () {
    describe('construction', function () {
        test('constructs with string error', function () {
            $failure = new Failure('error message');

            expect($failure->error())->toBe('error message');
        });

        test('constructs with exception error', function () {
            $exception = new RuntimeException('test error');
            $failure = new Failure($exception);

            expect($failure->error())->toBe($exception);
        });

        test('constructs with array error', function () {
            $errors = ['error1', 'error2'];
            $failure = new Failure($errors);

            expect($failure->error())->toBe($errors);
        });

        test('constructs with object error', function () {
            $obj = new stdClass();
            $obj->message = 'object error';
            $failure = new Failure($obj);

            expect($failure->error())->toBe($obj);
        });
    });

    describe('error()', function () {
        test('returns the wrapped error', function () {
            $error = 'test error';
            $failure = new Failure($error);

            expect($failure->error())->toBe($error);
        });

        test('returns exact exception reference', function () {
            $exception = new RuntimeException('original exception');
            $failure = new Failure($exception);

            expect($failure->error())->toBe($exception);
        });
    });

    describe('errorMessage()', function () {
        test('returns string error as-is', function () {
            $failure = new Failure('string error');

            expect($failure->errorMessage())->toBe('string error');
        });

        test('extracts message from Throwable', function () {
            $exception = new RuntimeException('exception message');
            $failure = new Failure($exception);

            expect($failure->errorMessage())->toBe('exception message');
        });

        test('calls __toString on Stringable objects', function () {
            $stringable = new class implements Stringable {
                public function __toString(): string {
                    return 'stringable message';
                }
            };
            $failure = new Failure($stringable);

            expect($failure->errorMessage())->toBe('stringable message');
        });

        test('calls __toString method if available', function () {
            $obj = new class {
                public function __toString(): string {
                    return 'toString message';
                }
            };
            $failure = new Failure($obj);

            expect($failure->errorMessage())->toBe('toString message');
        });

        test('calls toString method if available', function () {
            $obj = new class {
                public function toString(): string {
                    return 'toString method message';
                }
            };
            $failure = new Failure($obj);

            expect($failure->errorMessage())->toBe('toString method message');
        });

        test('calls toArray and json encodes if available', function () {
            $obj = new class {
                public function toArray(): array {
                    return ['error' => 'array error', 'code' => 123];
                }
            };
            $failure = new Failure($obj);

            expect($failure->errorMessage())->toBe('{"error":"array error","code":123}');
        });

        test('json encodes array values', function () {
            $failure = new Failure(['error1', 'error2']);

            expect($failure->errorMessage())->toBe('["error1","error2"]');
        });

        test('json encodes complex nested structures', function () {
            $complex = [
                'message' => 'Complex error',
                'details' => ['field' => 'validation failed'],
                'code' => 422
            ];
            $failure = new Failure($complex);

            expect($failure->errorMessage())->toBe('{"message":"Complex error","details":{"field":"validation failed"},"code":422}');
        });

        test('json encodes numeric values', function () {
            $failure = new Failure(404);

            expect($failure->errorMessage())->toBe('404');
        });

        test('json encodes boolean values', function () {
            $failure = new Failure(true);

            expect($failure->errorMessage())->toBe('true');
        });

        test('handles null values', function () {
            $failure = new Failure(null);

            expect($failure->errorMessage())->toBe('null');
        });

    });

    describe('exception()', function () {
        test('returns Throwable as-is', function () {
            $exception = new RuntimeException('original');
            $failure = new Failure($exception);

            expect($failure->exception())->toBe($exception);
        });

        test('converts string to RuntimeException', function () {
            $failure = new Failure('string error');
            $exception = $failure->exception();

            expect($exception)->toBeInstanceOf(RuntimeException::class);
            expect($exception->getMessage())->toBe('string error');
        });

        test('converts array to CompositeException', function () {
            $errors = [new RuntimeException('error1'), new RuntimeException('error2')];
            $failure = new Failure($errors);
            $exception = $failure->exception();

            expect($exception)->toBeInstanceOf(CompositeException::class);
        });

        test('converts other types to RuntimeException with message', function () {
            $obj = new class {
                public function __toString(): string {
                    return 'object error';
                }
            };
            $failure = new Failure($obj);
            $exception = $failure->exception();

            expect($exception)->toBeInstanceOf(RuntimeException::class);
            expect($exception->getMessage())->toBe('object error');
        });
    });

    describe('state methods', function () {
        test('isSuccess returns false', function () {
            $failure = new Failure('error');

            expect($failure->isSuccess())->toBeFalse();
        });

        test('isFailure returns true', function () {
            $failure = new Failure('error');

            expect($failure->isFailure())->toBeTrue();
        });
    });

    describe('error type preservation', function () {
        test('preserves string error type', function () {
            $failure = new Failure('string error');
            $error = $failure->error();

            expect($error)->toBeString();
            expect($error)->toBe('string error');
        });

        test('preserves integer error type', function () {
            $failure = new Failure(404);
            $error = $failure->error();

            expect($error)->toBeInt();
            expect($error)->toBe(404);
        });

        test('preserves array error type', function () {
            $errors = ['field1' => 'required', 'field2' => 'invalid'];
            $failure = new Failure($errors);
            $error = $failure->error();

            expect($error)->toBeArray();
            expect($error)->toBe($errors);
        });

        test('preserves exception error type', function () {
            $exception = new InvalidArgumentException('invalid argument');
            $failure = new Failure($exception);
            $error = $failure->error();

            expect($error)->toBeInstanceOf(InvalidArgumentException::class);
            expect($error)->toBe($exception);
        });
    });

    describe('readonly behavior', function () {
        test('Failure is readonly class', function () {
            $reflection = new ReflectionClass(Failure::class);

            expect($reflection->isReadOnly())->toBeTrue();
        });
    });
});