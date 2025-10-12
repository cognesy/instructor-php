<?php declare(strict_types=1);

use Cognesy\Stream\Sources\Array\ArrayStream;
use Cognesy\Stream\Transducers\Filter;
use Cognesy\Stream\Transducers\Map;
use Cognesy\Stream\Transducers\TakeN;
use Cognesy\Stream\TransformationStream;

test('TransducedStream applies transducers lazily to base stream', function () {
    $base = ArrayStream::from([1, 2, 3, 4]);
    $stream = TransformationStream::from($base)
        ->through(new Map(fn($x) => $x * 2))
        ->through(new Filter(fn($x) => $x > 4));
    $out = iterator_to_array($stream->getIterator(), false);
    expect($out)->toBe([6, 8]);
});

test('TransducedStream honors early termination', function () {
    $gen = (function () {
        yield from [10, 20, 30, 40];
    })();
    $stream = TransformationStream::from($gen)
        ->through(new Map(fn($x) => $x / 10))
        ->through(new TakeN(2));
    $out = iterator_to_array($stream->getIterator(), false);
    expect($out)->toBe([1, 2]);
});

