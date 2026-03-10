<?php declare(strict_types=1);

use Cognesy\Dynamic\StructureFactory;

it('parses nested type strings and descriptions with commas or colons', function () {
    $factory = new StructureFactory();

    $structure = $factory->fromString(
        'Payload',
        'pairs:array<int,string>, title:string(desc, more), note:string(hello:world)',
    );

    $jsonSchema = $structure->toJsonSchema();

    expect(array_keys($jsonSchema['properties']))->toBe(['pairs', 'title', 'note']);
    expect($jsonSchema['properties']['pairs']['type'] ?? null)->toBe('array');
    expect($jsonSchema['properties']['pairs']['items']['type'] ?? null)->toBe('string');
    expect($jsonSchema['properties']['title']['description'] ?? null)->toBe('desc, more');
    expect($jsonSchema['properties']['note']['description'] ?? null)->toBe('hello:world');
});
