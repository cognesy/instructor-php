<?php declare(strict_types=1);

use Cognesy\Stream\Iterator\BufferedTransformationIterator;
use Cognesy\Stream\Iterator\TransformationIterator;
use Cognesy\Stream\Sinks\ToArrayReducer;
use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transformation;

// SINGLE-PASS MODE (DEFAULT)

test('can iterate through transduction steps with foreach', function () {
    $source = [1, 2, 3];
    $iterator = (new Transformation())
        ->through(new Map(fn($x) => $x * 2))
        ->withSink(new ToArrayReducer())
        ->withInput($source)
        ->iterator();

    $steps = [];
    foreach ($iterator as $key => $intermediateResult) {
        $steps[$key] = $intermediateResult;
    }

    // Each step shows the accumulator building up
    expect($steps)->toBe([
        0 => [2],
        1 => [2, 4],
        2 => [2, 4, 6],
    ]);
});

test('iterator reports correct keys', function () {
    $source = ['a', 'b', 'c'];
    $iterator = (new Transformation())
        ->through(new Map(fn($x) => strtoupper($x)))
        ->withSink(new ToArrayReducer())
        ->withInput($source)
        ->iterator();

    $keys = [];
    foreach ($iterator as $key => $result) {
        $keys[] = $key;
    }

    expect($keys)->toBe([0, 1, 2]);
});

test('iterator valid returns false when exhausted', function () {
    $source = [1];
    $iterator = (new Transformation())
        ->withInput($source)
        ->iterator();

    foreach ($iterator as $result) {
        // Consume
    }

    expect($iterator->valid())->toBeFalse();
});

test('single-pass iterator cannot be rewound after iteration', function () {
    $source = [1, 2];
    $iterator = (new Transformation())
        ->withInput($source)
        ->iterator(buffered: false);

    // First iteration
    foreach ($iterator as $result) {
        break;
    }

    // Attempt second iteration (calls rewind)
    foreach ($iterator as $result) {
        // This should throw
    }
})->throws(LogicException::class, 'Cannot rewind single-pass transduction iterator');

test('returns TransductionIterator instance by default', function () {
    $iterator = (new Transformation())
        ->withInput([1, 2, 3])
        ->iterator();

    expect($iterator)->toBeInstanceOf(TransformationIterator::class);
});

test('returns BufferedTransductionIterator when buffered=true', function () {
    $iterator = (new Transformation())
        ->withInput([1, 2, 3])
        ->iterator(buffered: true);

    expect($iterator)->toBeInstanceOf(BufferedTransformationIterator::class);
});

test('can use iterator manually without foreach', function () {
    $source = [10, 20, 30];
    $iterator = (new Transformation())
        ->through(new Map(fn($x) => $x / 10))
        ->withSink(new ToArrayReducer())
        ->withInput($source)
        ->iterator();

    $results = [];
    $iterator->rewind();
    while ($iterator->valid()) {
        $results[] = $iterator->current();
        $iterator->next();
    }

    expect($results)->toBe([
        [1],
        [1, 2],
        [1, 2, 3],
    ]);
});

test('empty source produces no iterations', function () {
    $source = [];
    $iterator = (new Transformation())
        ->withInput($source)
        ->iterator();

    $count = 0;
    foreach ($iterator as $result) {
        $count++;
    }

    expect($count)->toBe(0);
});

test('can break from foreach and continue later', function () {
    $source = range(1, 10);
    $iterator = (new Transformation())
        ->through(new Map(fn($x) => $x * 2))
        ->withSink(new ToArrayReducer())
        ->withInput($source)
        ->iterator();

    $count = 0;
    foreach ($iterator as $result) {
        $count++;
        if ($count === 3) {
            break;
        }
    }

    expect($count)->toBe(3);
    expect($iterator->valid())->toBeTrue(); // Still has more steps
});

// BUFFERED MODE

test('buffered iterator can be rewound multiple times', function () {
    $source = [1, 2, 3];
    $iterator = (new Transformation())
        ->through(new Map(fn($x) => $x * 2))
        ->withSink(new ToArrayReducer())
        ->withInput($source)
        ->iterator(buffered: true);

    // First iteration
    $first = [];
    foreach ($iterator as $result) {
        $first[] = $result;
    }

    // Second iteration (should work)
    $second = [];
    foreach ($iterator as $result) {
        $second[] = $result;
    }

    expect($first)->toBe([
        [2],
        [2, 4],
        [2, 4, 6],
    ]);
    expect($second)->toBe($first);
});

test('buffered iterator stores results in memory', function () {
    $source = range(1, 5);
    $iterator = (new Transformation())
        ->through(new Map(fn($x) => $x * 10))
        ->withSink(new ToArrayReducer())
        ->withInput($source)
        ->iterator(buffered: true);

    // Consume fully
    iterator_to_array($iterator);

    // Rewind and consume again - should get same results
    $results = iterator_to_array($iterator);

    expect($results)->toBe([
        [10],
        [10, 20],
        [10, 20, 30],
        [10, 20, 30, 40],
        [10, 20, 30, 40, 50],
    ]);
});

test('buffered iterator allows partial consumption then rewind', function () {
    $source = [1, 2, 3, 4, 5];
    $iterator = (new Transformation())
        ->withSink(new ToArrayReducer())
        ->withInput($source)
        ->iterator(buffered: true);

    // Consume first 2
    $partial = [];
    foreach ($iterator as $result) {
        $partial[] = $result;
        if (count($partial) === 2) {
            break;
        }
    }
    expect($partial)->toHaveCount(2);

    // Rewind and consume all
    $full = iterator_to_array($iterator);
    expect($full)->toHaveCount(5);
});

test('buffered mode works with empty source', function () {
    $source = [];
    $iterator = (new Transformation())
        ->withInput($source)
        ->iterator(buffered: true);

    // First iteration
    $first = iterator_to_array($iterator);
    expect($first)->toBe([]);

    // Second iteration
    $second = iterator_to_array($iterator);
    expect($second)->toBe([]);
});

test('buffered iterator preserves results across rewinds', function () {
    $source = ['a', 'b', 'c'];
    $iterator = (new Transformation())
        ->through(new Map(fn($x) => strtoupper($x)))
        ->withSink(new ToArrayReducer())
        ->withInput($source)
        ->iterator(buffered: true);

    // Iterate 3 times
    $results = [];
    for ($i = 0; $i < 3; $i++) {
        $results[] = iterator_to_array($iterator);
    }

    // All should be identical
    expect($results[0])->toBe($results[1]);
    expect($results[1])->toBe($results[2]);
    expect($results[0])->toBe([
        ['A'],
        ['A', 'B'],
        ['A', 'B', 'C'],
    ]);
});
