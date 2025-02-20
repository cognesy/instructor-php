<?php

use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\ResultChain;

test('processing chain', function () {
    $result = ResultChain::make()
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => $payload * 2,
            fn($payload) => $payload - 3,
        ])
        ->process(5);
    expect($result)->toBe(9);
});

test('then callback', function () {
    $result = ResultChain::make()
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => $payload * 2,
        ])
        ->then(function (Result $result) {
            return $result->unwrap() * 2;
        })
        ->process(5);

    expect($result)->toBe(24);
});

it('on null processing result - fail (default)', function () {
    $result = ResultChain::make()
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => null, // This will cause the payload to skip further processing
            fn($payload) => $payload * 2,
        ])
        ->process(5);
    expect($result)->toBe(null);

    $result = ResultChain::for(5)
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => null, // This will cause the payload to skip further processing
            fn($payload) => $payload * 2,
        ])
        ->result();
    expect($result)->toBeInstanceOf(Result::class);
    expect($result->isFailure())->toBeTrue();
});

it('on null processing result - stop and return carry', function () {
    $result = ResultChain::make()
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => null, // This will cause the payload to skip further processing
            fn($payload) => $payload * 2,
        ], ResultChain::BREAK_ON_NULL)
        ->process(5);
    expect($result)->toBe(6);

    $result = ResultChain::for(5)
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => null, // This will cause the payload to skip further processing
            fn($payload) => $payload * 2,
        ], ResultChain::BREAK_ON_NULL)
        ->result();
    expect($result)->toBeInstanceOf(Result::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(6);
});

it('on null processing result - continue', function () {
    $result = ResultChain::make()
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => null, // This will cause the payload to skip further processing
            fn($payload) => $payload * 2,
        ], ResultChain::CONTINUE_ON_NULL)
        ->process(5);
    expect($result)->toBe(0);

    $result = ResultChain::for(5)
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => null, // This will cause the payload to skip further processing
            fn($payload) => $payload * 2,
        ], ResultChain::CONTINUE_ON_NULL)
        ->result();
    expect($result)->toBeInstanceOf(Result::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(0);
});

test('on error callback - Result::failure()', function () {
    $exception = null;

    $result = ResultChain::make()
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => Result::failure('Something went wrong'),
            fn($payload) => $payload * 2,
        ])
        ->onFailure(function(Failure $failure) use (&$exception) {
            $exception = $failure->error();
        })
        ->then(function (Result $result) {
            if ($result->isFailure()) {
                return 0;
            }
            return 99;
        })
        ->process(5);

    expect($result)->toBe(0);
});

test('on error callback - exception', function () {
    $exception = null;

    $result = ResultChain::make()
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => throw new Exception('Something went wrong'),
            fn($payload) => $payload * 2,
        ])
        ->onFailure(function(Result $result) use (&$exception) {
            $exception = $result->error();
        })
        ->then(function (Result $result) {
            return match(true) {
                $result->isFailure() => 0,
                default => $result->unwrap() * 2,
            };
        })
        ->process(5);

    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception->getMessage())->toBe('Something went wrong');
});
