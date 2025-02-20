<?php

use Cognesy\Utils\Json\ResilientJsonParser;

test('parse empty object', function () {
    $result = (new ResilientJsonParser('{}'))->parse();
    expect($result)->toBe([]);
});

test('parse empty array', function () {
    $result = (new ResilientJsonParser('[]'))->parse();
    expect($result)->toBe([]);
});

test('parse simple object', function () {
    $result = (new ResilientJsonParser('{"key": "value"}'))->parse();
    expect($result)->toBe(['key' => 'value']);
});

test('parse simple array', function () {
    $result = (new ResilientJsonParser('["value1", "value2"]'))->parse();
    expect($result)->toBe(['value1', 'value2']);
});

test('parse nested object', function () {
    $json = '{"outer": {"inner": "value"}}';
    $result = (new ResilientJsonParser($json))->parse();
    expect($result)->toBe(['outer' => ['inner' => 'value']]);
});

test('parse nested array', function () {
    $json = '["outer", ["inner1", "inner2"]]';
    $result = (new ResilientJsonParser($json))->parse();
    expect($result)->toBe(['outer', ['inner1', 'inner2']]);
});

test('parse numbers', function () {
    $json = '{"integer": 42, "float": 3.14, "exponent": 1.23e-4, "negative": -10, "large": 1234567890}';
    $result = (new ResilientJsonParser($json))->parse();
    expect($result)->toBe([
        'integer' => 42,
        'float' => 3.14,
        'exponent' => 1.23e-4,
        'negative' => -10,
        'large' => 1234567890
    ]);
});

test('parse boolean and null values', function () {
    $json = '{"bool_true": true, "bool_false": false, "null_value": null}';
    $result = (new ResilientJsonParser($json))->parse();
    expect($result)->toBe([
        'bool_true' => true,
        'bool_false' => false,
        'null_value' => null
    ]);
});

test('parse string with escaped characters', function () {
    $json = '{"escaped": "This is a \"quoted\" string with backslash and \t tab and \n newline"}';
    $result = (new ResilientJsonParser($json))->parse();
    expect($result['escaped'])->toBe("This is a \"quoted\" string with backslash and \t tab and \n newline");
});

test('parse string with code block', function () {
    $json = '{"code": "Here is some code: ```var x = 5;``` End of code."}';
    $result = (new ResilientJsonParser($json))->parse();
    expect($result['code'])->toBe('Here is some code: ```var x = 5;``` End of code.');
});

test('throw exception for invalid number', function () {
    $json = '{"invalid_number": 12.34.56}';
    expect(fn() => (new ResilientJsonParser($json))->parse())->toThrow(\RuntimeException::class);
});

test('throw exception for invalid boolean', function () {
    $json = '{"invalid_boolean": truefalse}';
    expect(fn() => (new ResilientJsonParser($json))->parse())->toThrow(\RuntimeException::class);
});

test('throw exception for unclosed object', function () {
    $json = '{"unclosed": "object"';
    expect(fn() => (new ResilientJsonParser($json))->parse())->toThrow(\RuntimeException::class);
});

test('throw exception for unclosed array', function () {
    $json = '["unclosed", "array"';
    expect(fn() => (new ResilientJsonParser($json))->parse())->toThrow(\RuntimeException::class);
});

test('throw exception for invalid JSON structure', function () {
    $json = '{"key": "value",}';  // Trailing comma
    expect(fn() => (new ResilientJsonParser($json))->parse())->toThrow(\RuntimeException::class);
});

test('parse empty string', function () {
    $result = (new ResilientJsonParser(''))->parse();
    expect($result)->toBe('');
});

test('parse whitespace-only string', function () {
    $result = (new ResilientJsonParser('    '))->parse();
    expect($result)->toBe('');
});

test('parse complex nested structure', function () {
    $json = '
    {
        "name": "John Doe",
        "age": 30,
        "isStudent": false,
        "courses": ["Math", "Physics", "Chemistry"],
        "address": {
            "street": "123 Main St",
            "city": "Anytown",
            "zipCode": "12345"
        },
        "grades": [
            {"subject": "Math", "score": 90},
            {"subject": "Physics", "score": 85},
            {"subject": "Chemistry", "score": 92}
        ]
    }';
    $result = (new ResilientJsonParser($json))->parse();
    expect($result)->toBe([
        "name" => "John Doe",
        "age" => 30,
        "isStudent" => false,
        "courses" => ["Math", "Physics", "Chemistry"],
        "address" => [
            "street" => "123 Main St",
            "city" => "Anytown",
            "zipCode" => "12345"
        ],
        "grades" => [
            ["subject" => "Math", "score" => 90],
            ["subject" => "Physics", "score" => 85],
            ["subject" => "Chemistry", "score" => 92]
        ]
    ]);
});

test('parse deeply nested structure', function () {
    $json = '{"level1":{"level2":{"level3":{"level4":{"level5":"deep value"}}}}}';
    $result = (new ResilientJsonParser($json))->parse();
    expect($result)->toBe([
        "level1" => [
            "level2" => [
                "level3" => [
                    "level4" => [
                        "level5" => "deep value"
                    ]
                ]
            ]
        ]
    ]);
});

test('handles known ACME cases', function ($json, $result) {
    expect((new ResilientJsonParser($json))->parse())->toMatchArray($result);
})->with([
    ['{"name":"ACME","year":2020}', ["name"=>"ACME","year"=>2020]],
    ['{"name":"ACME","year":2020} Other text', ["name"=>"ACME","year"=>2020]],
    ['{\n  "year": 2020,\n  "name": "ACME"\n}', ["name"=>"ACME","year"=>2020]],
    ['{\n  "name": "ACME",\n  "year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
    ['\n{\t  "name": "ACME",\n  "year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
    ['{\n"name": "ACME",\n"year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
    ['\n{"year": 2020, "name": "ACME"}\n', ["name"=>"ACME","year"=>2020]],
]);
