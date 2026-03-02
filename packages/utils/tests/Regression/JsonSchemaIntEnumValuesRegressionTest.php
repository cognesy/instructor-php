<?php declare(strict_types=1);

use Cognesy\Utils\JsonSchema\JsonSchema;

it('accepts integer enum values in json schema arrays', function () {
    $schema = JsonSchema::fromArray([
        'type' => 'integer',
        'enum' => [1, 2, 3],
    ]);

    expect($schema->enumValues())->toBe([1, 2, 3]);
    expect($schema->toArray()['enum'] ?? null)->toBe([1, 2, 3]);
});
