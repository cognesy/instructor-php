<?php declare(strict_types=1);

use Cognesy\Utils\Stream\Filesystem\FileStream;
use Cognesy\Utils\Stream\Json\JsonlStream;

test('JsonlStream decoded yields valid JSON objects only', function () {
    $tmp = new SplTempFileObject();
    $tmp->fwrite("{\"a\":1}\ninvalid\n{\"b\":2}\n\n");
    $fs = FileStream::fromFile($tmp);
    $stream = JsonlStream::fromFileStream($fs)->decoded();
    $rows = iterator_to_array($stream->getIterator(), false);
    expect($rows)->toBe([
        ['a' => 1],
        ['b' => 2],
    ]);
});

