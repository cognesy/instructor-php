<?php declare(strict_types=1);

use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

it('evaluates lazily and caches the first execution result', function () {
    $calls = 0;

    $pending = Pipeline::builder()
        ->through(function (int $value) use (&$calls): int {
            $calls++;

            return $value * 2;
        })
        ->create()
        ->executeWith(ProcessingState::with(10));

    expect($calls)->toBe(0)
        ->and($pending->value())->toBe(20)
        ->and($pending->value())->toBe(20)
        ->and($calls)->toBe(1);
});

it('creates isolated executions when iterating over inputs', function () {
    $pipeline = Pipeline::builder()
        ->through(fn(int $value): int => $value * 3)
        ->create()
        ->executeWith(ProcessingState::with(0));

    $values = [];

    foreach ($pipeline->each([1, 2, 3]) as $execution) {
        $values[] = $execution->value();
    }

    expect($values)->toBe([3, 6, 9]);
});

it('streams iterable results and returns an empty stream on failure', function () {
    $success = Pipeline::builder()
        ->create()
        ->executeWith(ProcessingState::with([1, 2, 3]));

    $failure = Pipeline::builder()
        ->through(fn(int $value): ?int => null)
        ->create()
        ->executeWith(ProcessingState::with(1));

    expect(iterator_to_array($success->stream()))->toBe([1, 2, 3])
        ->and(iterator_to_array($failure->stream()))->toBe([]);
});

it('returns default values from failed executions', function () {
    $pending = Pipeline::builder()
        ->through(fn(): never => throw new RuntimeException('broken'))
        ->create()
        ->executeWith(ProcessingState::with('test'));

    expect($pending->valueOr('fallback'))->toBe('fallback')
        ->and($pending->exception()?->getMessage())->toBe('broken');
});
