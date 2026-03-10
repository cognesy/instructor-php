<?php declare(strict_types=1);

use Cognesy\Dynamic\StructureFactory;

final class DynamicJsonSchemaMetadataProbe
{
    public string $name;
}

it('preserves root x-php-class metadata when exporting a structure to json schema', function () {
    $factory = new StructureFactory();
    $structure = $factory->fromClass(DynamicJsonSchemaMetadataProbe::class);

    $jsonSchema = $structure->toJsonSchema();

    expect($jsonSchema['x-php-class'] ?? null)->toBe(DynamicJsonSchemaMetadataProbe::class);
});

it('prefers standard title over x-title when reconstructing a structure from json schema', function () {
    $factory = new StructureFactory();

    $structure = $factory->fromJsonSchema([
        'type' => 'object',
        'title' => 'StandardTitle',
        'x-title' => 'LegacyTitle',
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ]);

    expect($structure->name())->toBe('StandardTitle');
});

it('falls back to x-title when standard title is missing', function () {
    $factory = new StructureFactory();

    $structure = $factory->fromJsonSchema([
        'type' => 'object',
        'x-title' => 'LegacyTitle',
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ]);

    expect($structure->name())->toBe('LegacyTitle');
});
