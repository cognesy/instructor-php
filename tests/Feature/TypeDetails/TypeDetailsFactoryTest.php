<?php

use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Symfony\Component\PropertyInfo\Type;

test('creates TypeDetails from type string', function () {
    $factory = new TypeDetailsFactory();

    $stringType = $factory->fromTypeName('string');
    $this->assertInstanceOf(TypeDetails::class, $stringType);
    $this->assertSame('string', $stringType->type);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Object type must have a class name');
    $factory->fromTypeName('object');
});

test('creates TypeDetails from PropertyInfo', function () {
    $factory = new TypeDetailsFactory();
    // Assuming you have a PropertyInfo instance $propertyInfo

    $propertyInfo = new Type('string', false, null, false, null, null);
    $typeDetails = $factory->fromPropertyInfo($propertyInfo);
    $this->assertInstanceOf(TypeDetails::class, $typeDetails);
    $this->assertSame('string', $typeDetails->type);
});

test('creates TypeDetails from value', function () {
    $factory = new TypeDetailsFactory();

    $stringType = $factory->fromValue('test');
    $this->assertInstanceOf(TypeDetails::class, $stringType);
    $this->assertSame('string', $stringType->type);
});

test('creates TypeDetails for scalar type', function () {
    $factory = new TypeDetailsFactory();

    $intType = $factory->scalarType('int');
    $this->assertInstanceOf(TypeDetails::class, $intType);
    $this->assertSame('int', $intType->type);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Unsupported scalar type: unknown');
    $factory->scalarType('unknown');
});

test('creates TypeDetails for array type', function () {
    $factory = new TypeDetailsFactory();

    // Test with array brackets
    $arrayType = $factory->arrayType('int[]');
    $this->assertInstanceOf(TypeDetails::class, $arrayType);
    $this->assertSame('array', $arrayType->type);
    $this->assertInstanceOf(TypeDetails::class, $arrayType->nestedType);
    $this->assertSame('int', $arrayType->nestedType->type);

    // Test without array brackets
    $arrayType = $factory->arrayType('int');
    $this->assertInstanceOf(TypeDetails::class, $arrayType);
    $this->assertSame('array', $arrayType->type);
    $this->assertInstanceOf(TypeDetails::class, $arrayType->nestedType);
    $this->assertSame('int', $arrayType->nestedType->type);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Class "unknown" does not exist');
    $factory->arrayType('unknown');
});

test('creates TypeDetails for object type', function () {
    $factory = new TypeDetailsFactory();

    $className = Tests\Examples\Schema\SimpleClass::class;
    $objectType = $factory->objectType($className);
    $this->assertInstanceOf(TypeDetails::class, $objectType);
    $this->assertSame('object', $objectType->type);
    $this->assertSame($className, $objectType->class);
});

test('creates TypeDetails for enum type', function () {
    $factory = new TypeDetailsFactory();

    $enumClassName = 'Tests\Examples\Schema\StringEnum';
    $enumType = $factory->enumType($enumClassName);
    $this->assertInstanceOf(TypeDetails::class, $enumType);
    $this->assertSame('enum', $enumType->type);
    $this->assertSame($enumClassName, $enumType->class);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Class "UnknownEnum" does not exist');
    $factory->enumType('UnknownEnum');
});