<?php

use Cognesy\Utils\Arrays;

test('flatten nested arrays with separator', function () {
    $nestedArray = [
        'apple',
        ['banana', 'orange'],
        ['grape', ['kiwi', 'mango']],
        'pear',
    ];

    $separator = ',';
    $expected = 'apple,banana,orange,grape,kiwi,mango,pear';

    $flattened = Arrays::flattenToString($nestedArray, $separator);

    expect($flattened)->toBe($expected);
});

test('flatten empty array', function () {
    $emptyArray = [];
    $separator = ',';

    $flattened = Arrays::flattenToString($emptyArray, $separator);

    expect($flattened)->toBe('');
});

test('flatten array with empty strings', function () {
    $arrayWithEmptyStrings = [
        'hello',
        '',
        'world',
        ['', 'foo', ''],
        'bar',
    ];

    $separator = ',';
    $expected = 'hello,world,foo,bar';

    $flattened = Arrays::flattenToString($arrayWithEmptyStrings, $separator);

    expect($flattened)->toBe($expected);
});

test('flatten array with non-string values', function () {
    $arrayWithNonStringValues = [
        'apple',
        ['banana', 123],
        ['grape', ['kiwi', 456.78]],
        'pear',
    ];

    $separator = ',';
    $expected = 'apple,banana,123,grape,kiwi,456.78,pear';

    $flattened = Arrays::flattenToString($arrayWithNonStringValues, $separator);

    expect($flattened)->toBe($expected);
});