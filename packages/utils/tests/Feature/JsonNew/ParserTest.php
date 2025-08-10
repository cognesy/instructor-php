<?php declare(strict_types=1);

use Cognesy\Utils\Json\Partial\LenientParser;

uses()->group('parser');

test('parses broken example with unclosed string and object', function () {
    $input = '{"a": "x", "b": {"c": "yy';
    $result = (new LenientParser())->parse($input);
    expect($result)->toBe(['a' => 'x', 'b' => ['c' => 'yy']]);
});

test('recovers trailing comma before object close', function () {
    $input = '{"a":1, "b":2,}';
    $result = (new LenientParser())->parse($input);
    expect($result)->toBe(['a' => 1, 'b' => 2]);
});

test('recovers unclosed array and object', function () {
    $input = '{"a":[1,2,3}';
    $result = (new LenientParser())->parse($input);
    // array closed, then object closed
    expect($result)->toBe(['a' => [1,2,3]]);
});

test('accepts bareword keys (coerced) and values', function () {
    $input = '{foo: bar, baz: 1}';
    $result = (new LenientParser())->parse($input);
    expect($result)->toBe(['foo' => 'bar', 'baz' => 1]);
});

test('pending key at EOF gets empty string value', function () {
    $input = '{"k"';
    $result = (new LenientParser())->parse($input);
    expect($result)->toBe(['k' => '']);
});

test('top-level scalar survives', function () {
    $input = '"hello';
    $result = (new LenientParser())->parse($input);
    expect($result)->toBe('hello');
});

test('mixed nested structures with partial numbers', function () {
    $input = '{"n": 12abc, "arr":[true, nul';
    $result = (new LenientParser())->parse($input);
    // 12abc → number_partial('12') -> 12; "nul" -> null (prefix tolerant)
    expect($result)->toBe(['n' => 12, 'arr' => [true, null]]);
});
