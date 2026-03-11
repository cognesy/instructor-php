<?php declare(strict_types=1);

use Cognesy\Utils\Json\StreamingPartialJson;

it('parses complete json directly to array', function () {
    $parsed = StreamingPartialJson::toArray('{"name":"John","age":30}');

    expect($parsed)->toBe(['name' => 'John', 'age' => 30]);
});

it('parses truncated json without roundtripping through Json facade', function () {
    $parsed = StreamingPartialJson::toArray('{"name":"John","age":');

    expect($parsed)->toBe(['name' => 'John', 'age' => null]);
});

it('returns null for empty or non-json input', function (string $input) {
    $parsed = StreamingPartialJson::toArray($input);

    expect($parsed)->toBeNull();
})->with([
    '',
    '   ',
    'not json',
]);
