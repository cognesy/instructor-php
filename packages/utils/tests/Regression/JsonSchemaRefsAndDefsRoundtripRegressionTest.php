<?php declare(strict_types=1);

use Cognesy\Utils\JsonSchema\JsonSchema;

it('round-trips $ref and $defs fields', function () {
    $root = JsonSchema::object(
        properties: [
            JsonSchema::any(name: 'user')->withRef('#/$defs/User'),
        ],
        requiredProperties: ['user'],
    )->withDef('User', JsonSchema::object(
        properties: [
            JsonSchema::string(name: 'id'),
        ],
        requiredProperties: ['id'],
    ));

    $array = $root->toArray();

    expect($array['properties']['user']['$ref'] ?? null)->toBe('#/$defs/User');
    expect($array['$defs']['User']['type'] ?? null)->toBe('object');
    expect($array['$defs']['User']['properties']['id']['type'] ?? null)->toBe('string');

    $roundtrip = JsonSchema::fromArray($array)->toArray();
    expect($roundtrip['properties']['user']['$ref'] ?? null)->toBe('#/$defs/User');
    expect($roundtrip['$defs']['User']['type'] ?? null)->toBe('object');
    expect($roundtrip['$defs']['User']['properties']['id']['type'] ?? null)->toBe('string');
});

it('can preserve exact raw document via JsonSchema::document', function () {
    $document = [
        'type' => 'object',
        'properties' => [
            'user' => ['$ref' => '#/$defs/User'],
        ],
        '$defs' => [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                ],
            ],
        ],
    ];

    $schema = JsonSchema::document($document);

    expect($schema->toArray())->toBe($document);
});
