<?php declare(strict_types=1);

use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Utils\JsonSchema\JsonSchema;

it('parses nullable object union property as object shape', function () {
    $schema = (new JsonSchemaParser())->parse(JsonSchema::fromArray([
        'type' => 'object',
        'properties' => [
            'meta' => [
                'type' => ['object', 'null'],
                'properties' => [
                    'requestId' => ['type' => 'string'],
                ],
            ],
        ],
    ]));

    expect($schema->properties['meta'] ?? null)->toBeInstanceOf(ArrayShapeSchema::class);
});

