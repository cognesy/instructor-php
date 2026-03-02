<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\Type;

it('normalizes data to schema-defined keys only', function () {
    $schema = SchemaBuilder::define('args')
        ->string('name')
        ->bool('active', required: false)
        ->schema();
    $record = Structure::fromSchema($schema);

    $normalized = $record->normalizeRecord([
        'name' => 'john',
        'active' => true,
        'ignored' => 'value',
    ]);

    expect($normalized)->toBe([
        'name' => 'john',
        'active' => true,
    ]);
});

it('applies defaults from schema metadata during normalization', function () {
    $citySchema = SchemaFactory::default()->fromType(
        type: Type::string(),
        name: 'city',
        hasDefaultValue: true,
        defaultValue: 'Warsaw',
    );
    $schema = SchemaBuilder::define('args')
        ->string('name')
        ->string('country', required: false)
        ->withProperty('city', $citySchema, required: false)
        ->schema();
    $record = Structure::fromSchema($schema);

    $normalized = $record->normalizeRecord([
        'name' => 'john',
        'unknown' => 'value',
    ]);

    expect($normalized)->toBe([
        'name' => 'john',
        'city' => 'Warsaw',
    ]);
});
