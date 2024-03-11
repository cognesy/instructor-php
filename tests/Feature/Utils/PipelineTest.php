<?php

use Cognesy\Instructor\Utils\Pipeline;

test('processing pipeline', function () {
    $pipeline = (new Pipeline())
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => $payload * 2,
            fn($payload) => $payload - 3,
        ]);

    $result = $pipeline->process(5);
    expect($result)->toBe(9);
});

test('before each callback', function () {
    $output = '';
    $pipeline = (new Pipeline())
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => $payload * 2,
        ])
        ->beforeEach(function ($payload) use (&$output) {
            $output .= "Before: $payload\n";
        });

    $pipeline->process(5);
    $expected = "Before: 5\nBefore: 6\n";
    expect($output)->toBe($expected);
});

test('after each callback', function () {
    $output = '';
    $pipeline = (new Pipeline())
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => $payload * 2,
        ])
        ->afterEach(function ($payload) use (&$output) {
            $output .= "After: $payload\n";
        });

    $pipeline->process(5);
    $expected = "After: 6\nAfter: 12\n";
    expect($output)->toBe($expected);
});

test('finish when callback', function () {
    $pipeline = (new Pipeline())
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => $payload * 2,
            fn($payload) => $payload - 5,
            fn($payload) => $payload * 2,
        ])
        ->finishWhen(function ($payload) {
            return $payload < 0;
        });

    $result = $pipeline->process(1);
    expect($result)->toBe(-1);
});

test('then callback', function () {
    $pipeline = (new Pipeline())
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => $payload * 2,
        ])
        ->then(function ($payload) {
            return $payload * 2;
        });

    $result = $pipeline->process(5);
    expect($result)->toBe(24);
});

test('on error callback', function () {
    $exception = null;
    $pipeline = (new Pipeline())
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => throw new Exception('Something went wrong'),
            fn($payload) => $payload * 2,
        ])
        ->onError(function ($e) use (&$exception) {
            $exception = $e;
            return 'Error handled';
        });

    $result = $pipeline->process(5);
    expect($result)->toBe('Error handled');
    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception->getMessage())->toBe('Something went wrong');
});

test('no error handler throws exception', function () {
    $pipeline = (new Pipeline())
        ->through([
            fn($payload) => $payload + 1,
            fn($payload) => throw new Exception('Something went wrong'),
            fn($payload) => $payload * 2,
        ]);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Something went wrong');

    $pipeline->process(5);
})->throws(Exception::class, 'Something went wrong');