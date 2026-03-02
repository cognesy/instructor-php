<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;

it('exposes record api schema data withData validate toArray', function () {
    $schema = SchemaBuilder::define('city')
        ->string('name')
        ->int('population')
        ->schema();
    $record = Structure::fromSchema($schema, ['name' => 'Wroclaw']);

    $updated = $record->withData(['name' => 'Wroclaw', 'population' => 670000]);

    expect($record->data())->toBe(['name' => 'Wroclaw'])
        ->and($updated->schema()->name())->toBe('city')
        ->and($updated->toArray())->toBe(['name' => 'Wroclaw', 'population' => 670000])
        ->and($updated->validate()->isValid())->toBeTrue();
});

it('fails validation when required field is missing', function () {
    $schema = SchemaBuilder::define('city')
        ->string('name')
        ->int('population')
        ->schema();
    $record = Structure::fromSchema($schema, ['name' => 'Wroclaw']);

    $validation = $record->validate();

    expect($validation->isInvalid())->toBeTrue()
        ->and($validation->getErrorMessage())->toContain('Missing required field');
});

it('supports schema-first creation api', function () {
    $schema = SchemaBuilder::define('user')
        ->string('name')
        ->int('age', required: false)
        ->schema();
    $record = Structure::fromSchema($schema);

    $populated = $record->fromArray(['name' => 'Ada']);

    expect($record->toArray())->toBe([])
        ->and($populated->toArray())->toBe(['name' => 'Ada'])
        ->and($populated->schema()->name())->toBe('user');
});
