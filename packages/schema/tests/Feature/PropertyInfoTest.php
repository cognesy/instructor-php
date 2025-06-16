<?php

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;
use Cognesy\Schema\Utils\PropertyInfo;
use Symfony\Component\TypeInfo\Type;

it('can get property name', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'testProperty');
    $name = $propertyInfo->getName();
    expect($name)->toEqual('testProperty');
});

it('can get property types', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'testProperty');
    $type = $propertyInfo->getType();
    expect($type)->toBeInstanceOf(Type::class);
});

it('can get property type', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'testProperty');
    $type = $propertyInfo->getType();
    expect($type)->toBeInstanceOf(Type::class);
});

it('can get property description via PhpDoc', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'testProperty');
    $description = $propertyInfo->getDescription();
    expect($description)->toBeString();
    expect($description)->toEqual('Property description');
});

it('can get property description via attribute', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'attributeProperty');
    $description = $propertyInfo->getDescription();
    expect($description)->toBeString();
    expect($description)->toEqual('Attribute description');
});

it('can check if property has attribute', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'attributeProperty');
    $hasAttribute = $propertyInfo->hasAttribute(Description::class);
    expect($hasAttribute)->toBeTrue();
});

it('can get attribute values', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'attributeProperty');
    $values = $propertyInfo->getAttributeValues(Description::class, 'text');
    expect($values)->toBeArray();
    expect($values[0])->toEqual('Attribute description');
});

it('can check if property is public', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'publicProperty');
    $isPublic = $propertyInfo->isPublic();
    expect($isPublic)->toBeTrue();
});

it('can check if property is nullable', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'nullableProperty');
    $isNullable = $propertyInfo->isNullable();
    expect($isNullable)->toBeTrue();
});

it('can check if property is read-only', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'readOnlyProperty');
    $isReadOnly = $propertyInfo->isReadOnly();
    expect($isReadOnly)->toBeTrue();
});
