<?php

use Cognesy\Schema\Tests\Examples\ClassInfo\EnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\IntEnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\StringEnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;
use Cognesy\Schema\Utils\ClassInfo;
use Cognesy\Schema\Utils\PropertyInfo;
use Symfony\Component\PropertyInfo\Type;

it('can get property type', function () {
    // Assuming TestClass has properties with defined types
    $type = (new ClassInfo(TestClassA::class))->getType('testProperty');
    expect($type)->toBeInstanceOf(Type::class);
    //expect($type->getName())->toEqual('string');
});

//it('throws exception for undefined property type', function () {
//    // Assuming TestClass has a property without a defined type
//    $this->classInfo->getType(TestClassA::class, 'undefinedProperty');
//})->throws(Exception::class, 'No type found for property');

it('can get class property names', function () {
    $properties = (new ClassInfo(TestClassA::class))->getPropertyNames();
    expect($properties)->toBeArray();
    expect($properties)->toContain('testProperty');
});

it('can get class properties', function () {
    $properties = (new ClassInfo(TestClassA::class))->getProperties();
    expect($properties)->toBeArray();
    expect($properties)->toHaveKey('testProperty');
    $property = $properties['testProperty'];
    expect($property)->toBeInstanceOf(PropertyInfo::class);
});

it('can get class description', function () {
    // Assuming TestClass has a PHPDoc description
    $description = (new ClassInfo(TestClassA::class))->getClassDescription();
    expect($description)->toBeString();
    expect($description)->toEqual('Class description');
});

it('can get property description', function () {
    // Assuming TestClass has properties with PHPDoc descriptions
    $description = (new ClassInfo(TestClassA::class))->getPropertyDescription('testProperty');
    expect($description)->toBeString();
});

it('can determine required properties', function () {
    // Assuming TestClass has at least one non-nullable property
    $requiredProperties = (new ClassInfo(TestClassA::class))->getRequiredProperties();
    expect($requiredProperties)->toBeArray();
    expect($requiredProperties)->toContain('nonNullableProperty');
    expect($requiredProperties)->not()->toContain('nullableProperty');
});

it('can check if property is public', function () {
    // Assuming TestClass has a public property
    $isPublic = (new ClassInfo(TestClassA::class))->isPublic('publicProperty');
    expect($isPublic)->toBeTrue();
});

it('can check if property is nullable', function () {
    // Assuming TestClass has a nullable property
    $isNullable = (new ClassInfo(TestClassA::class))->isNullable('nullableProperty');
    expect($isNullable)->toBeTrue();
});

it('can check if a class is an enum', function () {
    // Assuming EnumClass is an enum
    $isEnum = (new ClassInfo(EnumType::class))->isEnum();
    expect($isEnum)->toBeTrue();

    // Assuming TestClass is not an enum
    $isEnum = (new ClassInfo(TestClassA::class))->isEnum();
    expect($isEnum)->toBeFalse();
});


it('can check if an enum is backed', function () {
    // Assuming BackedEnumClass is a backed enum
    $isBackedEnum = (new ClassInfo(StringEnumType::class))->isBackedEnum();
    expect($isBackedEnum)->toBeTrue();

    // Assuming EnumClass is not a backed enum
    $isBackedEnum = (new ClassInfo(EnumType::class))->isBackedEnum();
    expect($isBackedEnum)->toBeFalse();
});

it('can get the backing type of an enum', function () {
    // Assuming BackedEnumClass is a backed enum with a string backing type
    $backingType = (new ClassInfo(StringEnumType::class))->enumBackingType();
    expect($backingType)->toEqual('string');
    $backingType = (new ClassInfo(IntEnumType::class))->enumBackingType();
    expect($backingType)->toEqual('int');
});

//it('can get the values of an enum', function () {
//    // Assuming EnumClass is an enum with defined values
//    $values = $this->classInfo->enumValues(EnumClass::class);
//    expect($values)->toBeArray();
//    expect($values)->toEqual(['VALUE1', 'VALUE2']); // Adjust expected values based on your EnumClass
//});
//
//it('can check if a class implements an interface', function () {
//    // Assuming TestClass implements TestInterface
//    $implementsInterface = $this->classInfo->implementsInterface(TestClassA::class, TestInterface::class);
//    expect($implementsInterface)->toBeTrue();
//
//    // Assuming AnotherClass does not implement TestInterface
//    $implementsInterface = $this->classInfo->implementsInterface(AnotherClass::class, TestInterface::class);
//    expect($implementsInterface)->toBeFalse();
//});