<?php declare(strict_types=1);

use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\SchemaBuilder;

it('builds object schema with required and optional properties', function () {
    $schema = SchemaBuilder::define('user', 'User profile')
        ->string('name', 'User name')
        ->int('age', 'User age', required: false)
        ->schema();

    expect($schema)->toBeInstanceOf(ObjectSchema::class)
        ->and($schema->name())->toBe('user')
        ->and($schema->description())->toBe('User profile')
        ->and($schema->hasProperty('name'))->toBeTrue()
        ->and($schema->hasProperty('age'))->toBeTrue()
        ->and($schema->required)->toBe(['name']);
});

it('supports nested shape definition with callable', function () {
    $schema = SchemaBuilder::define('order')
        ->shape(
            'address',
            fn(SchemaBuilder $builder) => $builder->string('street'),
            'Delivery address',
        )
        ->schema();

    $address = $schema->getPropertySchema('address');

    expect($address->description())->toBe('Delivery address')
        ->and($address->hasProperty('street'))->toBeTrue();
});

it('supports fluent option properties', function () {
    $schema = SchemaBuilder::define('person')
        ->option('gender', ['male', 'female'], 'Gender', required: false)
        ->schema();

    $gender = $schema->getPropertySchema('gender');

    expect($schema->required)->toBe([])
        ->and($gender->enumValues)->toBe(['male', 'female'])
        ->and($gender->description())->toBe('Gender');
});
