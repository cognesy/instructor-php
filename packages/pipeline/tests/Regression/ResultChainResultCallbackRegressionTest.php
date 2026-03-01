<?php declare(strict_types=1);

use Cognesy\Pipeline\Legacy\Chain\ResultChain;
use Cognesy\Utils\Result\Result;

// Guards regression from instructor-v97w (result callback passed as process input).
it('returns Result without callback using processed value', function () {
    $result = ResultChain::for(2)
        ->through(fn(int $value): int => $value * 2)
        ->result();

    expect($result)->toBeInstanceOf(Result::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(4);
});

it('treats result callback as Result transformer, not process input', function () {
    $result = ResultChain::for(2)
        ->through(fn(int $value): int => $value * 2)
        ->result(fn(Result $result): int => $result->unwrap() + 1);

    expect($result)->toBeInstanceOf(Result::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(5);
});
