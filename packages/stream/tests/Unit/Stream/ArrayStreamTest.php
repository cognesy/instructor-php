<?php declare(strict_types=1);

use Cognesy\Stream\Sources\Array\ArrayStream;

test('ArrayStream yields provided items in order', function () {
    $stream = ArrayStream::from([1, 2, 3]);
    $out = iterator_to_array($stream->getIterator(), false);
    expect($out)->toBe([1, 2, 3]);
});

test('ArrayStream handles empty arrays', function () {
    $stream = ArrayStream::from([]);
    $out = iterator_to_array($stream->getIterator(), false);
    expect($out)->toBe([]);
});

