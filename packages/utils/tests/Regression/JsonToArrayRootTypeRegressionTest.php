<?php declare(strict_types=1);

use Cognesy\Utils\Json\Json;

// Guards regression from instructor-vzq5 (scalar JSON roots causing return-type TypeError).
it('throws explicit InvalidArgumentException for scalar json roots', function (string $json, string $type) {
    expect(fn() => (new Json($json))->toArray())
        ->toThrow(\InvalidArgumentException::class, "Json::toArray expects JSON object or array as root type, got {$type}.");
})->with([
    'integer' => ['0', 'integer'],
    'boolean' => ['false', 'boolean'],
    'string' => ['"x"', 'string'],
    'null' => ['null', 'NULL'],
]);

it('keeps object and array roots valid for toArray', function (string $json, array $expected) {
    expect((new Json($json))->toArray())->toBe($expected);
})->with([
    'object' => ['{"name":"Ann"}', ['name' => 'Ann']],
    'array' => ['[1,2,3]', [1, 2, 3]],
]);
