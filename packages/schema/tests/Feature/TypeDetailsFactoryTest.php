<?php

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\TypeDetailsFactory;
use Cognesy\Schema\Tests\Examples\Schema\SimpleClass;
use Symfony\Component\TypeInfo\Type;

test('creates TypeDetails from type string', function () {
    $factory = new TypeDetailsFactory();

    $stringType = $factory->fromTypeName(TypeDetails::PHP_STRING);
    $this->assertInstanceOf(TypeDetails::class, $stringType);
    $this->assertSame(TypeDetails::PHP_STRING, $stringType->type);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Object type must have a class name');
    $factory->fromTypeName('object');
});

test('creates TypeDetails from TypeInfo', function () {
    $factory = new TypeDetailsFactory();
    // Assuming you have a PropertyInfo instance $propertyInfo

    //$typeInfo = new DeprecatedType(TypeDetails::PHP_STRING, false, null, false, null, null);
    $typeInfo = Type::string();
    $typeDetails = $factory->fromTypeInfo($typeInfo);
    $this->assertInstanceOf(TypeDetails::class, $typeDetails);
    $this->assertSame(TypeDetails::PHP_STRING, $typeDetails->type);
});

test('creates TypeDetails from value', function () {
    $factory = new TypeDetailsFactory();

    $stringType = $factory->fromValue('test');
    $this->assertInstanceOf(TypeDetails::class, $stringType);
    $this->assertSame(TypeDetails::PHP_STRING, $stringType->type);
});

test('creates TypeDetails for scalar type', function () {
    $factory = new TypeDetailsFactory();

    $intType = $factory->scalarType(TypeDetails::PHP_INT);
    $this->assertInstanceOf(TypeDetails::class, $intType);
    $this->assertSame(TypeDetails::PHP_INT, $intType->type);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Unsupported scalar type: unknown');
    $factory->scalarType('unknown');
});

test('creates TypeDetails for array type', function () {
    $factory = new TypeDetailsFactory();

    // Test with array
    $arrayType = $factory->arrayType();
    $this->assertInstanceOf(TypeDetails::class, $arrayType);
    $this->assertSame(TypeDetails::PHP_ARRAY, $arrayType->type);
    $this->assertSame(null, $arrayType->nestedType);
});

test('creates TypeDetails for collection type', function () {
    $factory = new TypeDetailsFactory();

    // Test with array brackets
    $collectionType = $factory->collectionType('int[]');
    $this->assertInstanceOf(TypeDetails::class, $collectionType);
    $this->assertSame(TypeDetails::PHP_COLLECTION, $collectionType->type);
    $this->assertInstanceOf(TypeDetails::class, $collectionType->nestedType);
    $this->assertSame(TypeDetails::PHP_INT, $collectionType->nestedType->type);

    // Test without array brackets
    $collectionType = $factory->collectionType(TypeDetails::PHP_INT);
    $this->assertInstanceOf(TypeDetails::class, $collectionType);
    $this->assertSame(TypeDetails::PHP_COLLECTION, $collectionType->type);
    $this->assertInstanceOf(TypeDetails::class, $collectionType->nestedType);
    $this->assertSame(TypeDetails::PHP_INT, $collectionType->nestedType->type);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Class "unknown" does not exist');
    $factory->fromTypeName('unknown[]');
});

test('creates TypeDetails for object type', function () {
    $factory = new TypeDetailsFactory();

    $className = SimpleClass::class;
    $objectType = $factory->objectType($className);
    $this->assertInstanceOf(TypeDetails::class, $objectType);
    $this->assertSame(TypeDetails::PHP_OBJECT, $objectType->type);
    $this->assertSame($className, $objectType->class);
});

test('creates TypeDetails for enum type', function () {
    $factory = new TypeDetailsFactory();

    $enumClassName = 'Cognesy\Schema\Tests\Examples\Schema\StringEnum';
    $enumType = $factory->enumType($enumClassName);
    $this->assertInstanceOf(TypeDetails::class, $enumType);
    $this->assertSame(TypeDetails::PHP_ENUM, $enumType->type);
    $this->assertSame($enumClassName, $enumType->class);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Class "UnknownEnum" does not exist');
    $factory->enumType('UnknownEnum');
});