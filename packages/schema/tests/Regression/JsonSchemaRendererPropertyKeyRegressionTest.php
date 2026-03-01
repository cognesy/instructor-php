<?php declare(strict_types=1);

use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\JsonSchemaRenderer;
use Symfony\Component\TypeInfo\Type;

it('renders object property keys from properties map keys, not nested schema names', function () {
    $schema = new ObjectSchema(
        type: Type::object(\stdClass::class),
        name: 'Root',
        description: '',
        properties: [
            'external_key' => new Schema(Type::string(), 'internal_name'),
        ],
        required: ['external_key'],
    );

    $json = (new JsonSchemaRenderer())->toArray($schema);
    $properties = $json['properties'] ?? [];

    expect($properties)->toHaveKey('external_key');
    expect($properties)->not->toHaveKey('internal_name');
});

it('renders array-shape property keys from properties map keys, not nested schema names', function () {
    $schema = new ArrayShapeSchema(
        type: Type::array(),
        name: 'Shape',
        description: '',
        properties: [
            'external_key' => new Schema(Type::string(), 'internal_name'),
        ],
        required: ['external_key'],
    );

    $json = (new JsonSchemaRenderer())->toArray($schema);
    $properties = $json['properties'] ?? [];

    expect($properties)->toHaveKey('external_key');
    expect($properties)->not->toHaveKey('internal_name');
});

