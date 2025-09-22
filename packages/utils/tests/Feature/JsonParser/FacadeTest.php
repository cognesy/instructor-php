<?php declare(strict_types=1);

use Cognesy\Utils\Json\Partial\ResilientJson;

uses()->group('facade');

test('fast path valid JSON', function () {
    $input = '  ignore me {"a":1,"b":[2,3]}';
    $out = ResilientJson::parse($input);
    expect($out)->toBe(['a'=>1,'b'=>[2,3]]);
});

test('repair then decode: trailing comma and missing brace', function () {
    $input = '{"a":1,"b":2,';
    $out = ResilientJson::parse($input);
    expect($out)->toBe(['a'=>1,'b'=>2]);
});

test('fallback lenient parser for tricky case', function () {
    $input = '{"a": "x", "b": {"c": "yy';
    $out = ResilientJson::parse($input);
    expect($out)->toBe(['a'=>'x','b'=>['c'=>'yy']]);
});
