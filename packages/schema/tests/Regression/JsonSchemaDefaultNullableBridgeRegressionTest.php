<?php declare(strict_types=1);

use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Schema\SchemaBuilder;
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\Type;

it('renders nullable and default metadata for schema properties', function () {
    $factory = SchemaFactory::default();

    $nested = SchemaBuilder::define('settings')
        ->withProperty('theme', $factory->fromType(Type::string(), 'theme', hasDefaultValue: true, defaultValue: 'dark'), required: false)
        ->schema();

    $schema = SchemaBuilder::define('user')
        ->withProperty('name', $factory->fromType(Type::string(), 'name'))
        ->withProperty('nickname', $factory->propertySchema(Type::string(), 'nickname', '', nullable: true, hasDefaultValue: true, defaultValue: null), required: false)
        ->withProperty('age', $factory->fromType(Type::int(), 'age', hasDefaultValue: true, defaultValue: 18), required: false)
        ->withProperty('tags', $factory->fromType(Type::list(Type::string()), 'tags', hasDefaultValue: true, defaultValue: []), required: false)
        ->withProperty('settings', SchemaFactory::withMetadata($nested, name: 'settings'), required: false)
        ->schema();

    $json = $factory->toJsonSchema($schema);

    expect($json['properties']['nickname']['nullable'] ?? null)->toBeTrue();
    expect(array_key_exists('default', $json['properties']['nickname'] ?? []))->toBeTrue();
    expect($json['properties']['nickname']['default'])->toBeNull();

    expect($json['properties']['age']['default'] ?? null)->toBe(18);
    expect($json['properties']['tags']['default'] ?? null)->toBe([]);
    expect($json['properties']['settings']['properties']['theme']['default'] ?? null)->toBe('dark');
});

it('parses and roundtrips nullable and default metadata from json schema', function () {
    $json = [
        'type' => 'object',
        'x-php-class' => stdClass::class,
        'properties' => [
            'nickname' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'age' => [
                'type' => 'integer',
                'default' => 18,
            ],
            'tags' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'default' => [],
            ],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'theme' => [
                        'type' => 'string',
                        'default' => 'dark',
                    ],
                ],
                'required' => [],
            ],
        ],
        'required' => [],
    ];

    $schema = (new JsonSchemaParser())->fromJsonSchema($json, 'user', 'User');

    $nickname = $schema->properties['nickname'];
    $age = $schema->properties['age'];
    $tags = $schema->properties['tags'];
    $theme = $schema->properties['settings']->getPropertySchema('theme');

    expect($nickname->isNullable())->toBeTrue();
    expect($nickname->hasDefaultValue())->toBeTrue();
    expect($nickname->defaultValue())->toBeNull();

    expect($age->hasDefaultValue())->toBeTrue();
    expect($age->defaultValue())->toBe(18);

    expect($tags->hasDefaultValue())->toBeTrue();
    expect($tags->defaultValue())->toBe([]);

    expect($theme->hasDefaultValue())->toBeTrue();
    expect($theme->defaultValue())->toBe('dark');

    $roundtrip = SchemaFactory::default()->toJsonSchema($schema);

    expect(array_key_exists('default', $roundtrip['properties']['nickname'] ?? []))->toBeTrue();
    expect($roundtrip['properties']['nickname']['default'])->toBeNull();
    expect($roundtrip['properties']['age']['default'] ?? null)->toBe(18);
    expect($roundtrip['properties']['tags']['default'] ?? null)->toBe([]);
    expect($roundtrip['properties']['settings']['properties']['theme']['default'] ?? null)->toBe('dark');
});
