<?php declare(strict_types=1);

use Cognesy\Utils\Stream\Array\ArrayStream;
use Cognesy\Utils\Stream\TransducedStream;
use Cognesy\Utils\Transducer\Transducers\Filter;
use Cognesy\Utils\Transducer\Transducers\Map;
use Cognesy\Utils\Transducer\Transducers\TakeN;

test('TransducedStream applies transducers lazily to base stream', function () {
    $base = ArrayStream::from([1, 2, 3, 4]);
    $stream = TransducedStream::from($base, new Map(fn($x) => $x * 2), new Filter(fn($x) => $x > 4));
    $out = iterator_to_array($stream->getIterator(), false);
    expect($out)->toBe([6, 8]);
});

test('TransducedStream honors early termination', function () {
    $gen = (function () {
        yield from [10, 20, 30, 40];
    })();
    $stream = TransducedStream::from($gen, new Map(fn($x) => $x / 10), new TakeN(2));
    $out = iterator_to_array($stream->getIterator(), false);
    expect($out)->toBe([1, 2]);
});

