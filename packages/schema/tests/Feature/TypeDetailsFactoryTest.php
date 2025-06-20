<?php

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Schema\Tests\Examples\Schema\SimpleClass;

test('creates TypeDetails from type string', function () {
    $stringType = TypeDetails::fromTypeName(TypeDetails::PHP_STRING);
    $this->assertInstanceOf(TypeDetails::class, $stringType);
    $this->assertSame(TypeDetails::PHP_STRING, $stringType->type());

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Object type must have a class name');
    TypeDetails::fromTypeName(TypeDetails::PHP_OBJECT);
});

test('creates TypeDetails from PropertyInfo', function () {
    $propertyInfo = PropertyInfo::fromReflection(
        new \ReflectionProperty(SimpleClass::class, 'stringVar')
    );
    $typeDetails = $propertyInfo->getTypeDetails();
    $this->assertInstanceOf(TypeDetails::class, $typeDetails);
    $this->assertSame(TypeDetails::PHP_STRING, $typeDetails->type());
});

test('creates TypeDetails from value', function () {
    $stringType = TypeDetails::fromValue('test');
    $this->assertInstanceOf(TypeDetails::class, $stringType);
    $this->assertSame(TypeDetails::PHP_STRING, $stringType->type());
});

test('creates TypeDetails for scalar type', function () {
    $intType = TypeDetails::scalar(TypeDetails::PHP_INT);
    $this->assertInstanceOf(TypeDetails::class, $intType);
    $this->assertSame(TypeDetails::PHP_INT, $intType->type());

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Unsupported scalar type: unknown');
    TypeDetails::scalar('unknown');
});

test('creates TypeDetails for array type', function () {
    // Test with array
    $arrayType = TypeDetails::array();
    $this->assertInstanceOf(TypeDetails::class, $arrayType);
    $this->assertSame(TypeDetails::PHP_ARRAY, $arrayType->type());
    $this->assertSame(null, $arrayType->nestedType());
});

test('creates TypeDetails for collection type', function () {
    // Test with array brackets
    $collectionType = TypeDetails::collection('int[]');
    $this->assertInstanceOf(TypeDetails::class, $collectionType);
    $this->assertSame(TypeDetails::PHP_COLLECTION, $collectionType->type());
    $this->assertInstanceOf(TypeDetails::class, $collectionType->nestedType());
    $this->assertSame(TypeDetails::PHP_INT, $collectionType->nestedType->type());

    // Test without array brackets
    $collectionType = TypeDetails::collection(TypeDetails::PHP_INT);
    $this->assertInstanceOf(TypeDetails::class, $collectionType);
    $this->assertSame(TypeDetails::PHP_COLLECTION, $collectionType->type());
    $this->assertInstanceOf(TypeDetails::class, $collectionType->nestedType());
    $this->assertSame(TypeDetails::PHP_INT, $collectionType->nestedType->type());

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Class "unknown" does not exist');
    TypeDetails::fromTypeName('unknown[]');
});

test('creates TypeDetails for object type', function () {
    $className = SimpleClass::class;
    $objectType = TypeDetails::object($className);
    $this->assertInstanceOf(TypeDetails::class, $objectType);
    $this->assertSame(TypeDetails::PHP_OBJECT, $objectType->type());
    $this->assertSame($className, $objectType->class());
});

test('creates TypeDetails for enum type', function () {
    $enumClassName = 'Cognesy\Schema\Tests\Examples\Schema\StringEnum';
    $enumType = TypeDetails::enum($enumClassName);
    $this->assertInstanceOf(TypeDetails::class, $enumType);
    $this->assertSame(TypeDetails::PHP_ENUM, $enumType->type());
    $this->assertSame($enumClassName, $enumType->class());

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Class "UnknownEnum" does not exist');
    TypeDetails::enum('UnknownEnum');
});