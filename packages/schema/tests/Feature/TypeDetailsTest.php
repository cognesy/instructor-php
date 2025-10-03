<?php

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

test('returns correct string representation', function () {
    $stringType = new TypeDetails('string');
    $this->assertSame('string', (string) $stringType);

    /** @var class-string $objectClass */
    $objectClass = 'Foo\Bar';
    $objectType = new TypeDetails('object', $objectClass);
    $this->assertSame('Foo\Bar', (string) $objectType);

    /** @var class-string $enumClass */
    $enumClass = 'Foo\Bar';
    $enumType = new TypeDetails('enum', $enumClass, null, 'string', ['foo', 'bar']);
    $this->assertSame('Foo\Bar', (string) $enumType);

    $collectionType = new TypeDetails('collection', null, new TypeDetails('int'));
    $this->assertSame('int[]', (string) $collectionType);

    $arrayType = new TypeDetails('array', null, null);
    $this->assertSame('array', (string) $arrayType);
});

test('returns correct JSON type', function () {
    $stringType = new TypeDetails('string');
    $this->assertSame('string', $stringType->toJsonType()->toString());

    /** @var class-string $objectClass */
    $objectClass = 'Foo\Bar';
    $objectType = new TypeDetails('object', $objectClass);
    $this->assertSame('object', $objectType->toJsonType()->toString());

    /** @var class-string $enumClass */
    $enumClass = 'Foo\Bar';
    $enumType = new TypeDetails('enum', $enumClass, null, 'int', [1, 2, 3]);
    $this->assertSame('integer', $enumType->toJsonType()->toString());

    $collectionType = new TypeDetails('collection', null, new TypeDetails('bool'));
    $this->assertSame('array', $collectionType->toJsonType()->toString());

    $arrayType = new TypeDetails('array', null, null);
    $this->assertSame('array', $arrayType->toJsonType()->toString());

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Unsupported type: unknown');
    $unknownType = new TypeDetails('unknown');
    $unknownType->toJsonType();
});

test('returns correct short name', function () {
    $stringType = new TypeDetails('string');
    $this->assertSame('string', $stringType->shortName());

    /** @var class-string $objectClass */
    $objectClass = 'Foo\Bar';
    $objectType = new TypeDetails('object', $objectClass);
    $this->assertSame('Bar', $objectType->shortName());

    /** @var class-string $enumClass */
    $enumClass = 'Foo\Bar';
    $enumType = new TypeDetails('enum', $enumClass, null, 'string', ['foo', 'bar']);
    $this->assertSame('one of: foo, bar', $enumType->shortName());

    $collectionType = new TypeDetails('collection', null, new TypeDetails('int'));
    $this->assertSame('int[]', $collectionType->shortName());

    $arrayType = new TypeDetails('array', null, new TypeDetails('int'));
    $this->assertSame('array', $arrayType->shortName());
});

test('returns correct class only name', function () {
    /** @var class-string $objectClass */
    $objectClass = 'Foo\Bar';
    $objectType = new TypeDetails('object', $objectClass);
    $this->assertSame('Bar', $objectType->classOnly());

    /** @var class-string $enumClass */
    $enumClass = 'Foo\Bar';
    $enumType = new TypeDetails('enum', $enumClass, null, 'string', ['foo', 'bar']);
    $this->assertSame('Bar', $enumType->classOnly());

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Trying to get class name for type that is not an object or enum');
    $stringType = new TypeDetails('string');
    $stringType->classOnly();
});

test('converts JSON type to PHP type', function () {
    $this->assertSame('object', TypeDetails::jsonToPhpType(JsonSchemaType::object()));
    $this->assertSame('array', TypeDetails::jsonToPhpType(JsonSchemaType::array()));
    $this->assertSame('int', TypeDetails::jsonToPhpType(JsonSchemaType::integer()));
    $this->assertSame('float', TypeDetails::jsonToPhpType(JsonSchemaType::number()));
    $this->assertSame('string', TypeDetails::jsonToPhpType(JsonSchemaType::string()));
    $this->assertSame('bool', TypeDetails::jsonToPhpType(JsonSchemaType::boolean()));

//    $this->expectException(Exception::class);
//    $this->expectExceptionMessage('Unknown type: unknown');
//    TypeDetails::fromJsonType('unknown');
});