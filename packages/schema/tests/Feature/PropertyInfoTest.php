<?php

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Tests\Examples\ClassInfo\TestClassA;
use Cognesy\Schema\Utils\PropertyInfo;

it('can get property name', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'mixedProperty');
    $name = $propertyInfo->getName();
    expect($name)->toEqual('mixedProperty');
});

it('can get property types', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'mixedProperty');
    $type = $propertyInfo->getTypeDetails();
    expect($type)->toBeInstanceOf(TypeDetails::class);
    expect($type->type())->toEqual('mixed');
});

it('can get property type', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'mixedProperty');
    $type = $propertyInfo->getTypeDetails();
    expect($type)->toBeInstanceOf(TypeDetails::class);
    expect($type->type())->toEqual('mixed');
});

it('can get property description via PhpDoc', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'mixedProperty');
    $description = $propertyInfo->getDescription();
    expect($description)->toBeString();
    expect($description)->toEqual('Property description');
});

it('can get property description via attribute', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'attributeMixedProperty');
    $description = $propertyInfo->getDescription();
    expect($description)->toBeString();
    expect($description)->toEqual('Attribute description');
});

it('can check if property has attribute', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'attributeMixedProperty');
    $hasAttribute = $propertyInfo->hasAttribute(Description::class);
    expect($hasAttribute)->toBeTrue();
});

it('can get attribute values', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'attributeMixedProperty');
    $values = $propertyInfo->getAttributeValues(Description::class, 'text');
    expect($values)->toBeArray();
    expect($values[0])->toEqual('Attribute description');
});

it('can check if property is public', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'explicitMixedProperty');
    $isPublic = $propertyInfo->isPublic();
    expect($isPublic)->toBeTrue();
});

it('can check if property is nullable', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'nullableIntProperty');
    $isNullable = $propertyInfo->isNullable();
    expect($isNullable)->toBeTrue();
});

it('can check if property is read-only', function () {
    $propertyInfo = PropertyInfo::fromName(TestClassA::class, 'readOnlyStringProperty');
    $isReadOnly = $propertyInfo->isReadOnly();
    expect($isReadOnly)->toBeTrue();
});
