<?php

use Cognesy\Schema\Exceptions\ReflectionException;
use Cognesy\Schema\Reflection\ClassInfo;
use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Schema\Tests\Examples\ClassInfo\EnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\IntEnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\StringEnumType;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;
use Symfony\Component\TypeInfo\Type;

it('can get property type', function () {
    $type = ClassInfo::fromString(TestClassA::class)->getPropertyType('mixedProperty');
    expect($type)->toBeInstanceOf(Type::class);
    expect((string) $type)->toBe('mixed');
});

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

it('throws domain exception for unknown class', function () {
    expect(fn() => ClassInfo::fromString('Unknown\\MissingClass'))
        ->toThrow(ReflectionException::class, 'Cannot create ClassInfo');
});

it('throws domain exception for unknown property', function () {
    $classInfo = ClassInfo::fromString(TestClassA::class);
    expect(fn() => $classInfo->getProperty('missingProperty'))
        ->toThrow(ReflectionException::class, 'Property `missingProperty` not found');
});

it('throws domain exception for invalid filter', function () {
    $classInfo = ClassInfo::fromString(TestClassA::class);
    expect(fn() => $classInfo->getFilteredProperties([123]))
        ->toThrow(ReflectionException::class, 'Filter must be a callable.');
});
