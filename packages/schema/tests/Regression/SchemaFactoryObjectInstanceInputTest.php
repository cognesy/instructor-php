<?php declare(strict_types=1);

use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;
use Cognesy\Schema\TypeInfo;

it('supports plain object instances passed to schema()', function () {
    $factory = new SchemaFactory();

    /** @var ObjectSchema $schema */
    $schema = $factory->schema(new TestClassA('readonly'));

    expect($schema)->toBeInstanceOf(ObjectSchema::class);
    expect(TypeInfo::className($schema->type))->toBe(TestClassA::class);
    expect($schema->getPropertyNames())->toBe([
        'mixedProperty',
        'attributeMixedProperty',
        'nonNullableIntProperty',
        'explicitMixedProperty',
        'nullableIntProperty',
        'readOnlyStringProperty',
    ]);
});
