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

    $flattened = Arrays::flatten($nestedArray, $separator);

    expect($flattened)->toBe($expected);
});

test('flatten empty array', function () {
    $emptyArray = [];
    $separator = ',';

    $flattened = Arrays::flatten($emptyArray, $separator);

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

    $flattened = Arrays::flatten($arrayWithEmptyStrings, $separator);

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

    $flattened = Arrays::flatten($arrayWithNonStringValues, $separator);

    expect($flattened)->toBe($expected);
});