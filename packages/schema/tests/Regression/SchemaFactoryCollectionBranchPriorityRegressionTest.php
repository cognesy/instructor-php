<?php declare(strict_types=1);

use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\EnumSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\ScalarSchema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\Tests\Examples\Schema\ComplexClass;

it('maps typed array properties to CollectionSchema before enum/object branches', function () {
    $schema = (new SchemaFactory())->schema(ComplexClass::class);

    $enumCollection = $schema->properties['arrayOfStringEnums'];
    $objectCollection = $schema->properties['arrayOfSimpleObjects'];
    $scalarCollection = $schema->properties['arrayOfInts'];

    expect($enumCollection)->toBeInstanceOf(CollectionSchema::class);
    expect($enumCollection->nestedItemSchema)->toBeInstanceOf(EnumSchema::class);

    expect($objectCollection)->toBeInstanceOf(CollectionSchema::class);
    expect($objectCollection->nestedItemSchema)->toBeInstanceOf(ObjectSchema::class);

    expect($scalarCollection)->toBeInstanceOf(CollectionSchema::class);
    expect($scalarCollection->nestedItemSchema)->toBeInstanceOf(ScalarSchema::class);
});
