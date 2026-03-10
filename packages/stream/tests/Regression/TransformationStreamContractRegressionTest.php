<?php declare(strict_types=1);

use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;
use Cognesy\Stream\Sinks\ToStringReducer;
use Cognesy\Stream\Transform\Map\Transducers\Map;

it('returns emitted stream items even when the provided transformation has a custom sink', function () {
    $transformation = Transformation::define(
        new Map(fn($x) => $x * 2),
    )->withSink(new ToStringReducer(separator: ','));

    $stream = TransformationStream::from([1, 2, 3])->using($transformation);

    expect($stream->getCompleted())->toBe([2, 4, 6]);
});

it('rebuilds execution when deriving a stream after iteration has started', function () {
    $stream = TransformationStream::from([1, 2, 3])
        ->through(new Map(fn($x) => $x * 2));

    foreach ($stream as $_) {
        break;
    }

    $derived = $stream->through(new Map(fn($x) => $x + 1));

    expect(iterator_to_array($derived, false))->toBe([3, 5, 7]);
});
