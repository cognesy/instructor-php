<?php declare(strict_types=1);

use Cognesy\Utils\JsonSchema\JsonSchema;

it('preserves composed schemas when parsing from arrays', function () {
    $document = [
        'oneOf' => [
            ['type' => 'string', 'title' => 'AsString'],
            ['type' => 'integer', 'title' => 'AsInteger'],
        ],
        'description' => 'Union schema',
    ];

    $schema = JsonSchema::fromArray($document);

    expect($schema->toArray())->toBe($document);
});

it('preserves allOf schemas when parsing from arrays', function () {
    $document = [
        'allOf' => [
            ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
            ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        ],
    ];

    $schema = JsonSchema::fromArray($document);

    expect($schema->toArray())->toBe($document);
});
