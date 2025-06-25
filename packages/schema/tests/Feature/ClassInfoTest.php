<?php

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Reflection\ClassInfo;
use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Schema\Tests\Examples\ClassInfo\EnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\IntEnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\StringEnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;

it('can get property type', function () {
    // Assuming TestClass has properties with defined types
    $type = ClassInfo::fromString(TestClassA::class)->getPropertyTypeDetails('mixedProperty');
    expect($type)->toBeInstanceOf(TypeDetails::class);
    expect($type->type)->toEqual('mixed');
});

//it('throws exception for undefined property type', function () {
//    // Assuming TestClass has a property without a defined type
//    $this->classInfo->getType(TestClassA::class, 'undefinedProperty');
//})->throws(Exception::class, 'No type found for property');

it('can get class property names', function () {
    $properties = ClassInfo::fromString(TestClassA::class)->getPropertyNames();
    expect($properties)->toBeArray();
    expect($properties)->toContain('mixedProperty');
});

it('can get class properties', function () {
    $properties = ClassInfo::fromString(TestClassA::class)->getProperties();
    expect($properties)->toBeArray();
    expect($properties)->toHaveKey('mixedProperty');
    $property = $properties['mixedProperty'];
    expect($property)->toBeInstanceOf(PropertyInfo::class);
});

it('can get class description', function () {
    // Assuming TestClass has a PHPDoc description
    $description = ClassInfo::fromString(TestClassA::class)->getClassDescription();
    expect($description)->toBeString();
    expect($description)->toEqual('Class description');
});

it('can get property description', function () {
    // Assuming TestClass has properties with PHPDoc descriptions
    $description = ClassInfo::fromString(TestClassA::class)->getPropertyDescription('mixedProperty');
    expect($description)->toBeString();
});

it('can determine required properties', function () {
    // Assuming TestClass has at least one non-nullable property
    $requiredProperties = ClassInfo::fromString(TestClassA::class)->getRequiredProperties();
    expect($requiredProperties)->toBeArray();
    expect($requiredProperties)->toContain('nonNullableIntProperty');
    expect($requiredProperties)->not()->toContain('nullableIntProperty');
});

it('can check if property is public', function () {
    // Assuming TestClass has a public property
    $isPublic = ClassInfo::fromString(TestClassA::class)->isPublic('explicitMixedProperty');
    expect($isPublic)->toBeTrue();
});

it('can check if property is nullable', function () {
    // Assuming TestClass has a nullable property
    $isNullable = ClassInfo::fromString(TestClassA::class)->isNullable('nullableIntProperty');
    expect($isNullable)->toBeTrue();
});

it('can check if a class is an enum', function () {
    // Assuming EnumClass is an enum
    $isEnum = ClassInfo::fromString(EnumType::class)->isEnum();
    expect($isEnum)->toBeTrue();

    // Assuming TestClass is not an enum
    $isEnum = ClassInfo::fromString(TestClassA::class)->isEnum();
    expect($isEnum)->toBeFalse();
});


it('can check if an enum is backed', function () {
    // Assuming BackedEnumClass is a backed enum
    $isBackedEnum = ClassInfo::fromString(StringEnumType::class)->isBacked();
    expect($isBackedEnum)->toBeTrue();

    // Assuming EnumClass is not a backed enum
    $isBackedEnum = ClassInfo::fromString(EnumType::class)->isBacked();
    expect($isBackedEnum)->toBeFalse();
});

it('can get the backing type of an enum', function () {
    // Assuming BackedEnumClass is a backed enum with a string backing type
    $backingType = ClassInfo::fromString(StringEnumType::class)->enumBackingType();
    expect($backingType)->toEqual('string');
    $backingType = ClassInfo::fromString(IntEnumType::class)->enumBackingType();
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