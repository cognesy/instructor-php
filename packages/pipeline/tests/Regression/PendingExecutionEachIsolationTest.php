<?php declare(strict_types=1);

use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

it('materialized each() outputs preserve per-input results', function () {
    $pending = Pipeline::builder()
        ->through(fn(int $x): int => $x * 2)
        ->create()
        ->executeWith(ProcessingState::empty());

    $executions = iterator_to_array($pending->each([1, 2, 3]), false);
    $values = array_map(
        static fn($execution): mixed => $execution->value(),
        $executions
    );

    expect($values)->toBe([2, 4, 6]);
});

it('each() yields independent PendingExecution instances', function () {
    $pending = Pipeline::builder()
        ->through(fn(int $x): int => $x + 1)
        ->create()
        ->executeWith(ProcessingState::empty());

    $executions = iterator_to_array($pending->each([10, 20]), false);
    $ids = array_map(
        static fn(object $execution): int => spl_object_id($execution),
        $executions
    );

    expect($ids[0])->not()->toBe($ids[1]);
});
