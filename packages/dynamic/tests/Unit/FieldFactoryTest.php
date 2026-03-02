<?php declare(strict_types=1);

use Cognesy\Dynamic\FieldFactory;
use Cognesy\Schema\SchemaFactory;

it('reuses schema factory instance across field creation calls', function () {
    $factoryProperty = new ReflectionProperty(FieldFactory::class, 'schemaFactory');
    $factoryProperty->setValue(null, null);

    FieldFactory::fromTypeName('id', 'int');
    $firstFactory = $factoryProperty->getValue();

    FieldFactory::fromTypeName('name', 'string');
    $secondFactory = $factoryProperty->getValue();

    expect($firstFactory)->toBeInstanceOf(SchemaFactory::class)
        ->and($secondFactory)->toBe($firstFactory);
});
