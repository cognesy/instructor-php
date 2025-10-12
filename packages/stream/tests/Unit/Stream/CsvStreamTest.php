<?php declare(strict_types=1);

use Cognesy\Stream\Sources\Csv\CsvStream;

test('CsvStream rowsAssoc maps headers to values', function () {
    $tmp = new SplTempFileObject();
    $tmp->fwrite("a,b\n1,2\n3,4\n");
    $csv = CsvStream::fromFile($tmp)->rowsAssoc();
    $rows = iterator_to_array($csv->getIterator(), false);
    expect($rows)->toBe([
        ['a' => '1', 'b' => '2'],
        ['a' => '3', 'b' => '4'],
    ]);
});

