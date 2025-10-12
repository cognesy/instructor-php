<?php declare(strict_types=1);

use Cognesy\Stream\Sources\Array\ArrayStream;
use Cognesy\Stream\Transducers\Filter;
use Cognesy\Stream\Transducers\Map;
use Cognesy\Stream\Transducers\TakeN;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;

// Feature tests illustrating desired DX using the existing API.
// These tests demonstrate composing transducers and applying them to streams,
// while exposing current rough edges in ergonomics.

test('TransducedStream with reusable pipeline array applied to a stream', function () {
    $base = ArrayStream::from([1, 2, 3, 4, 5]);

    $pipeline = [
        new Map(fn(int $x): int => $x * 2),
        new Filter(fn(int $x): bool => $x > 5),
        new TakeN(3),
    ];

    $stream = TransformationStream::from($base)->through(...$pipeline);
    $out = iterator_to_array($stream->getIterator(), false);

    expect($out)->toBe([6, 8, 10]);
});

test('Transduce chaining to define a pipeline and apply to a Stream', function () {
    $base = ArrayStream::from([1, 2, 3, 4, 5]);

    $result = (new Transformation())
        ->through(
            new Map(fn(int $x): int => $x * 2),
            new Filter(fn(int $x): bool => $x > 5),
            new TakeN(3),
        )
        ->executeOn($base);

    expect($result)->toBe([6, 8, 10]);
});

test('Compose two transducer pipelines by concatenation of arrays', function () {
    $base = ArrayStream::from(['  foo', 'bar  ', '', 'baz   ']);

    $sanitize = [
        new Map(fn(string $s): string => trim($s)),
        new Filter(fn(string $s): bool => $s !== ''),
    ];

    $enrich = [
        new Map(fn(string $s): string => strtoupper($s)),
    ];

    $combined = [...$sanitize, ...$enrich];

    $stream = TransformationStream::from($base)->through(...$combined);
    $out = iterator_to_array($stream->getIterator(), false);

    expect($out)->toBe(['FOO', 'BAR', 'BAZ']);
});

test('Build a pipeline from a stream of operations (current gap: collect first)', function () {
    // A stream producing transducer ops dynamically
    $ops = ArrayStream::from([
        new Map(fn(int $x): int => $x + 1),
        new Filter(fn(int $x): bool => $x % 2 === 0),
        new TakeN(2),
    ]);

    // Current gap: must collect ops to an array before applying
    $opsArray = iterator_to_array($ops->getIterator(), false);

    $data = ArrayStream::from([1, 2, 3, 4, 5, 6]);
    $stream = TransformationStream::from($data)->through(...$opsArray);
    $out = iterator_to_array($stream->getIterator(), false);

    expect($out)->toBe([2, 4]);
});

test('Laziness with generator input and early termination via TakeN', function () {
    $gen = (function (): \Generator {
        foreach (range(1, 1000) as $i) {
            yield $i;
        }
    })();

    $stream = TransformationStream::from($gen)
        ->through(
            new Map(fn(int $x): int => $x * 10),
            new TakeN(2),
        );

    $out = iterator_to_array($stream->getIterator(), false);
    expect($out)->toBe([10, 20]);
});

