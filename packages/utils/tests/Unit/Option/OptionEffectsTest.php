<?php declare(strict_types=1);

use Cognesy\Utils\Option\Option;

describe('Option effects and observation', function () {
    describe('ifSome()', function () {
        it('executes callback when Some and returns self', function () {
            $executed = false;
            $capturedValue = null;

            $option = Option::some('test');
            $result = $option->ifSome(function ($value) use (&$executed, &$capturedValue) {
                $executed = true;
                $capturedValue = $value;
            });

            expect($executed)->toBeTrue();
            expect($capturedValue)->toBe('test');
            expect($result)->toBe($option);
        });

        it('does not execute callback when None and returns self', function () {
            $executed = false;

            $option = Option::none();
            $result = $option->ifSome(function ($value) use (&$executed) {
                $executed = true;
            });

            expect($executed)->toBeFalse();
            expect($result)->toBe($option);
        });

        it('allows method chaining', function () {
            $executions = [];

            $result = Option::some(5)
                ->ifSome(function ($value) use (&$executions) {
                    $executions[] = 'first';
                })
                ->map(fn($x) => $x * 2)
                ->ifSome(function ($value) use (&$executions) {
                    $executions[] = 'second';
                });

            expect($executions)->toBe(['first', 'second']);
            expect($result->toNullable())->toBe(10);
        });

        it('handles side effects safely', function () {
            $log = [];

            Option::some('value')
                ->ifSome(function ($value) use (&$log) {
                    $log[] = "Processing: $value";
                });

            expect($log)->toBe(['Processing: value']);
        });
    });

    describe('ifNone()', function () {
        it('executes callback when None and returns self', function () {
            $executed = false;

            $option = Option::none();
            $result = $option->ifNone(function () use (&$executed) {
                $executed = true;
            });

            expect($executed)->toBeTrue();
            expect($result)->toBe($option);
        });

        it('does not execute callback when Some and returns self', function () {
            $executed = false;

            $option = Option::some('test');
            $result = $option->ifNone(function () use (&$executed) {
                $executed = true;
            });

            expect($executed)->toBeFalse();
            expect($result)->toBe($option);
        });

        it('allows method chaining', function () {
            $executions = [];

            $result = Option::none()
                ->ifNone(function () use (&$executions) {
                    $executions[] = 'none';
                })
                ->orElse(Option::some('default'))
                ->ifSome(function ($value) use (&$executions) {
                    $executions[] = 'some';
                });

            expect($executions)->toBe(['none', 'some']);
        });

        it('handles logging or cleanup operations', function () {
            $log = [];

            Option::none()
                ->ifNone(function () use (&$log) {
                    $log[] = 'Value was not found';
                });

            expect($log)->toBe(['Value was not found']);
        });
    });

    describe('combined effects', function () {
        it('executes appropriate callbacks based on state', function () {
            $someExecuted = false;
            $noneExecuted = false;

            Option::some('test')
                ->ifSome(function () use (&$someExecuted) {
                    $someExecuted = true;
                })
                ->ifNone(function () use (&$noneExecuted) {
                    $noneExecuted = true;
                });

            expect($someExecuted)->toBeTrue();
            expect($noneExecuted)->toBeFalse();
        });

        it('works in transformation pipelines', function () {
            $sideEffects = [];

            $result = Option::some(10)
                ->ifSome(function ($value) use (&$sideEffects) {
                    $sideEffects[] = "Input: $value";
                })
                ->filter(fn($x) => $x > 5)
                ->ifSome(function ($value) use (&$sideEffects) {
                    $sideEffects[] = "Filtered: $value";
                })
                ->map(fn($x) => $x * 2)
                ->ifSome(function ($value) use (&$sideEffects) {
                    $sideEffects[] = "Mapped: $value";
                });

            expect($sideEffects)->toBe(['Input: 10', 'Filtered: 10', 'Mapped: 20']);
            expect($result->toNullable())->toBe(20);
        });
    });
});