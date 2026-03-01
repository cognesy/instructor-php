<?php declare(strict_types=1);

use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

it('captures TypeError as failed state with ContinueWithFailure strategy', function () {
    $pending = Pipeline::builder(ErrorStrategy::ContinueWithFailure)
        ->through(fn(int $x): int => $x * 2)
        ->create()
        ->executeWith(ProcessingState::with('invalid-input'));

    $state = $pending->state();

    expect($state->isFailure())->toBeTrue()
        ->and($state->exception())->toBeInstanceOf(TypeError::class);
});

it('rethrows TypeError with FailFast strategy', function () {
    $pending = Pipeline::builder(ErrorStrategy::FailFast)
        ->through(fn(int $x): int => $x * 2)
        ->create()
        ->executeWith(ProcessingState::with('invalid-input'));

    expect(fn() => $pending->state())->toThrow(TypeError::class);
});
