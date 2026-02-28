<?php declare(strict_types=1);

use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;

it('supports plain object instances passed to schema()', function () {
    $factory = new SchemaFactory();

    /** @var ObjectSchema $schema */
    $schema = $factory->schema(new TestClassA('readonly'));

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect($schema->typeDetails->class)->toBe(TestClassA::class);
    expect($schema->getPropertyNames())->toBe([
        'mixedProperty',
        'attributeMixedProperty',
        'nonNullableIntProperty',
        'explicitMixedProperty',
        'nullableIntProperty',
        'readOnlyStringProperty',
    ]);
});

