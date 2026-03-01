<?php declare(strict_types=1);

use Cognesy\Stream\TransformationStream;

it('treats empty array as valid source for getIterator', function () {
    $stream = TransformationStream::from([]);

    $items = iterator_to_array($stream->getIterator(), false);

    expect($items)->toBe([]);
});

it('treats empty array as valid source for getCompleted', function () {
    $stream = TransformationStream::from([]);

    expect($stream->getCompleted())->toBe([]);
});
