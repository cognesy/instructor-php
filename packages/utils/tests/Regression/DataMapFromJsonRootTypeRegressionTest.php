<?php declare(strict_types=1);

use Cognesy\Utils\Data\DataMap;

// Guards regression from instructor-m9na (valid scalar JSON caused raw TypeError).
it('throws explicit InvalidArgumentException for valid scalar json roots', function (string $json, string $type) {
    expect(fn() => DataMap::fromJson($json))
        ->toThrow(\InvalidArgumentException::class, "DataMap::fromJson expects JSON object or array as root type, got {$type}.");
})->with([
    'integer' => ['1', 'integer'],
    'boolean' => ['true', 'boolean'],
    'string' => ['"x"', 'string'],
    'null' => ['null', 'NULL'],
]);

it('accepts empty json object as valid root', function () {
    $map = DataMap::fromJson('{}');

    expect($map->toArray())->toBe([]);
});
