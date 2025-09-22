<?php

use Cognesy\Utils\Result\Result;

describe('Result side effects', function () {
    describe('ifSuccess()', function () {
        test('executes callback on Success', function () {
            $executed = false;
            $capturedValue = null;

            $result = Result::success('test value');
            $returned = $result->ifSuccess(function($value) use (&$executed, &$capturedValue) {
                $executed = true;
                $capturedValue = $value;
            });

            expect($executed)->toBeTrue();
            expect($capturedValue)->toBe('test value');
            expect($returned)->toBe($result);
        });

        test('does not execute callback on Failure', function () {
            $executed = false;

            $result = Result::failure('error');
            $returned = $result->ifSuccess(function($value) use (&$executed) {
                $executed = true;
            });

            expect($executed)->toBeFalse();
            expect($returned)->toBe($result);
        });

        test('returns same instance for method chaining', function () {
            $result = Result::success('test');
            $returned = $result->ifSuccess(fn($value) => null);

            expect($returned)->toBe($result);
        });

        test('allows method chaining with multiple ifSuccess calls', function () {
            $calls = [];

            $result = Result::success('test')
                ->ifSuccess(function($value) use (&$calls) {
                    $calls[] = 'first';
                })
                ->ifSuccess(function($value) use (&$calls) {
                    $calls[] = 'second';
                });

            expect($calls)->toBe(['first', 'second']);
            expect($result)->toBeInstanceOf(\Cognesy\Utils\Result\Success::class);
        });
    });

    describe('ifFailure()', function () {
        test('executes callback on Failure', function () {
            $executed = false;
            $capturedException = null;

            $result = Result::failure('test error');
            $returned = $result->ifFailure(function($exception) use (&$executed, &$capturedException) {
                $executed = true;
                $capturedException = $exception;
            });

            expect($executed)->toBeTrue();
            expect($capturedException)->toBeInstanceOf(\RuntimeException::class);
            expect($capturedException->getMessage())->toBe('test error');
            expect($returned)->toBe($result);
        });

        test('does not execute callback on Success', function () {
            $executed = false;

            $result = Result::success('value');
            $returned = $result->ifFailure(function($exception) use (&$executed) {
                $executed = true;
            });

            expect($executed)->toBeFalse();
            expect($returned)->toBe($result);
        });

        test('returns same instance for method chaining', function () {
            $result = Result::failure('error');
            $returned = $result->ifFailure(fn($exception) => null);

            expect($returned)->toBe($result);
        });

        test('receives actual exception for Throwable errors', function () {
            $originalException = new InvalidArgumentException('original');
            $capturedThrowable = null;

            $result = Result::failure($originalException);
            $result->ifFailure(function($throwable) use (&$capturedThrowable) {
                $capturedThrowable = $throwable;
            });

            expect($capturedThrowable)->toBe($originalException);
        });

        test('receives converted exception for string errors', function () {
            $capturedThrowable = null;

            $result = Result::failure('string error');
            $result->ifFailure(function($throwable) use (&$capturedThrowable) {
                $capturedThrowable = $throwable;
            });

            expect($capturedThrowable)->toBeInstanceOf(\RuntimeException::class);
            expect($capturedThrowable->getMessage())->toBe('string error');
        });
    });

    describe('method chaining between ifSuccess and ifFailure', function () {
        test('allows chaining ifSuccess and ifFailure on Success', function () {
            $successCalled = false;
            $failureCalled = false;

            Result::success('value')
                ->ifSuccess(function($value) use (&$successCalled) {
                    $successCalled = true;
                })
                ->ifFailure(function($exception) use (&$failureCalled) {
                    $failureCalled = true;
                });

            expect($successCalled)->toBeTrue();
            expect($failureCalled)->toBeFalse();
        });

        test('allows chaining ifSuccess and ifFailure on Failure', function () {
            $successCalled = false;
            $failureCalled = false;

            Result::failure('error')
                ->ifSuccess(function($value) use (&$successCalled) {
                    $successCalled = true;
                })
                ->ifFailure(function($exception) use (&$failureCalled) {
                    $failureCalled = true;
                });

            expect($successCalled)->toBeFalse();
            expect($failureCalled)->toBeTrue();
        });

        test('allows multiple chained calls', function () {
            $log = [];

            Result::success('test')
                ->ifSuccess(function($value) use (&$log) {
                    $log[] = 'success1';
                })
                ->ifFailure(function($exception) use (&$log) {
                    $log[] = 'failure1';
                })
                ->ifSuccess(function($value) use (&$log) {
                    $log[] = 'success2';
                })
                ->ifFailure(function($exception) use (&$log) {
                    $log[] = 'failure2';
                });

            expect($log)->toBe(['success1', 'success2']);
        });
    });
});