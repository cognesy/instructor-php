<?php declare(strict_types=1);

use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

it('treats nullable scalar union as scalar type', function () {
    $type = JsonSchemaType::fromJsonData([
        'type' => ['string', 'null'],
    ]);

    expect($type->isString())->toBeTrue()
        ->and($type->isScalar())->toBeTrue();
});

it('infers nullable for union types containing null', function () {
    $schema = JsonSchema::fromArray([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ['string', 'null']],
        ],
        'required' => ['name'],
    ]);

    $name = $schema->property('name');

    expect($name?->isNullable())->toBeTrue()
        ->and($name?->type()->isString())->toBeTrue();
});

it('renders nullable union property as scalar with nullable flag', function () {
    $schema = JsonSchema::fromArray([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ['string', 'null']],
        ],
        'required' => ['name'],
    ]);

    $array = $schema->toArray();

    expect($array['properties']['name']['type'] ?? null)->toBe('string')
        ->and($array['properties']['name']['nullable'] ?? null)->toBeTrue();
});

