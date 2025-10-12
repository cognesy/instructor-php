<?php declare(strict_types=1);

use Cognesy\Utils\Stream\Text\TextStream;

test('TextStream chars yields bytes', function () {
    $s = TextStream::from('abc');
    expect(iterator_to_array($s->chars()->getIterator(), false))->toBe(['a', 'b', 'c']);
});

test('TextStream lines splits by EOL and can drop empty', function () {
    $s = TextStream::from("a\n\n b\n");
    $linesKeep = iterator_to_array($s->lines("\n", false)->getIterator(), false);
    $linesDrop = iterator_to_array($s->lines("\n", true)->getIterator(), false);
    expect($linesKeep)->toBe(['a', '', ' b', '']);
    expect($linesDrop)->toBe(['a', ' b']);
});

test('TextStream words extracts tokens by regex', function () {
    $s = TextStream::from('Hello, world! 123');
    $words = iterator_to_array($s->words('\\w+')->getIterator(), false);
    expect($words)->toBe(['Hello', 'world', '123']);
});

