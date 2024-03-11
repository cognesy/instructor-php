<?php

use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Symfony\Component\PropertyInfo\Type;
use Tests\Examples\ClassInfo\TestClassA;

beforeEach(function () {
    $this->classInfo = new ClassInfo();
});

it('can get property types', function () {
    // Assuming TestClass has properties with defined types
    $types = $this->classInfo->getTypes(TestClassA::class, 'testProperty');
    expect($types)->toBeArray();
    expect($types[0])->toBeInstanceOf(Type::class);
});

//it('throws exception for undefined property type', function () {
//    // Assuming TestClass has a property without a defined type
//    $this->classInfo->getType(TestClassA::class, 'undefinedProperty');
//})->throws(Exception::class, 'No type found for property');

it('can get class properties', function () {
    $properties = $this->classInfo->getProperties(TestClassA::class);
    expect($properties)->toBeArray();
    expect($properties)->toContain('testProperty');
});

it('can get class description', function () {
    // Assuming TestClass has a PHPDoc description
    $description = $this->classInfo->getClassDescription(TestClassA::class);
    expect($description)->toBeString();
    expect($description)->toEqual('Class description');
});

it('can get property description', function () {
    // Assuming TestClass has properties with PHPDoc descriptions
    $description = $this->classInfo->getPropertyDescription(TestClassA::class, 'testProperty');
    expect($description)->toBeString();
});

it('can determine required properties', function () {
    // Assuming TestClass has at least one non-nullable property
    $requiredProperties = $this->classInfo->getRequiredProperties(TestClassA::class);
    expect($requiredProperties)->toBeArray();
    expect($requiredProperties)->toContain('nonNullableProperty');
});

it('can check if property is public', function () {
    // Assuming TestClass has a public property
    $isPublic = $this->classInfo->isPublic(TestClassA::class, 'publicProperty');
    expect($isPublic)->toBeTrue();
});

it('can check if property is nullable', function () {
    // Assuming TestClass has a nullable property
    $isNullable = $this->classInfo->isNullable(TestClassA::class, 'nullableProperty');
    expect($isNullable)->toBeTrue();
});

//it('can check if a class is an enum', function () {
//    // Assuming EnumClass is an enum
//    $isEnum = $this->classInfo->isEnum(EnumClass::class);
//    expect($isEnum)->toBeTrue();
//
//    // Assuming TestClass is not an enum
//    $isEnum = $this->classInfo->isEnum(TestClassA::class);
//    expect($isEnum)->toBeFalse();
//});
//
//it('can check if an enum is backed', function () {
//    // Assuming BackedEnumClass is a backed enum
//    $isBackedEnum = $this->classInfo->isBackedEnum(BackedEnumClass::class);
//    expect($isBackedEnum)->toBeTrue();
//
//    // Assuming EnumClass is not a backed enum
//    $isBackedEnum = $this->classInfo->isBackedEnum(EnumClass::class);
//    expect($isBackedEnum)->toBeFalse();
//});
//
//it('can get the backing type of an enum', function () {
//    // Assuming BackedEnumClass is a backed enum with a string backing type
//    $backingType = $this->classInfo->enumBackingType(BackedEnumClass::class);
//    expect($backingType)->toEqual('string');
//});
//
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