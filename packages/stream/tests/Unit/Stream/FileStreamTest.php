<?php declare(strict_types=1);

use Cognesy\Stream\Sources\Filesystem\FileStream;

test('FileStream lines reads lines from SplTempFileObject', function () {
    $tmp = new SplTempFileObject();
    $tmp->fwrite("a\n\n b\n");
    $fs = FileStream::fromFile($tmp);

    $keep = iterator_to_array($fs->lines(false)->getIterator(), false);

    $tmp2 = new SplTempFileObject();
    $tmp2->fwrite("a\n\n b\n");
    $fs2 = FileStream::fromFile($tmp2);
    $drop = iterator_to_array($fs2->lines(true)->getIterator(), false);

    expect($keep)->toBe(['a', '', ' b']);
    expect($drop)->toBe(['a', ' b']);
});

test('FileStream chunks reads fixed-size blocks', function () {
    $tmp = new SplTempFileObject();
    $tmp->fwrite(str_repeat('x', 10));
    $fs = FileStream::fromFile($tmp);

    $chunks = iterator_to_array($fs->chunks(4)->getIterator(), false);
    expect($chunks)->toBe(['xxxx', 'xxxx', 'xx']);
});

