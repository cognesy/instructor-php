<?php declare(strict_types=1);

use Cognesy\Dynamic\StructureBuilder;
use Cognesy\Schema\Data\ObjectSchema;

it('builds object schema with required and optional fields', function () {
    $builder = StructureBuilder::define('user', 'User profile')
        ->string('name', 'User name')
        ->int('age', 'User age', required: false);

    $schema = $builder->schema();

    expect($schema)->toBeInstanceOf(ObjectSchema::class)
        ->and($schema->name())->toBe('user')
        ->and($schema->description())->toBe('User profile')
        ->and($schema->hasProperty('name'))->toBeTrue()
        ->and($schema->hasProperty('age'))->toBeTrue()
        ->and($schema->required)->toBe(['name']);
});

it('builds structure records from builder', function () {
    $record = StructureBuilder::define('payload')
        ->string('query')
        ->bool('strict', required: false)
        ->build(['query' => 'abc']);

    expect($record->toArray())->toBe(['query' => 'abc'])
        ->and($record->schema()->name())->toBe('payload');
});

it('forwards explicit description to nested structure schema', function () {
    $schema = StructureBuilder::define('order')
        ->structure(
            'address',
            fn(StructureBuilder $builder) => $builder->string('street'),
            'Delivery address',
        )
        ->schema();

    $addressSchema = $schema->getPropertySchema('address');

    expect($addressSchema->description())->toBe('Delivery address');
});

it('supports fluent option fields', function () {
    $schema = StructureBuilder::define('person')
        ->option('gender', ['male', 'female'], 'Gender', required: false)
        ->schema();

    $gender = $schema->getPropertySchema('gender');

    expect($schema->required)->toBe([])
        ->and($gender->enumValues)->toBe(['male', 'female'])
        ->and($gender->description())->toBe('Gender');
});
