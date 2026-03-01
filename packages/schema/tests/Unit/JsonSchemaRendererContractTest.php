<?php declare(strict_types=1);

use Cognesy\Schema\JsonSchemaRenderer;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Utils\JsonSchema\JsonSchema;

it('returns JsonSchema object from renderer contract', function () {
    $schema = (new SchemaFactory())->string('value', 'Value');
    $rendered = (new JsonSchemaRenderer())->render($schema);

    expect($rendered)->toBeInstanceOf(JsonSchema::class);
    expect($rendered->toArray()['type'] ?? null)->toBe('string');
    expect($rendered->toArray()['description'] ?? null)->toBe('Value');
});

