<?php

use Cognesy\Instructor\Features\Schema\Data\TypeDetails;

test('returns correct string representation', function () {
    $stringType = new TypeDetails('string');
    $this->assertSame('string', (string) $stringType);

    $objectType = new TypeDetails('object', 'Foo\Bar');
    $this->assertSame('Foo\Bar', (string) $objectType);

    $enumType = new TypeDetails('enum', 'Foo\Bar', null, 'string', ['foo', 'bar']);
    $this->assertSame('Foo\Bar', (string) $enumType);

    $collectionType = new TypeDetails('collection', null, new TypeDetails('int'));
    $this->assertSame('int[]', (string) $collectionType);

    $arrayType = new TypeDetails('array', null, null);
    $this->assertSame('array', (string) $arrayType);
});

test('returns correct JSON type', function () {
    $stringType = new TypeDetails('string');
    $this->assertSame('string', $stringType->jsonType());

    $objectType = new TypeDetails('object', 'Foo\Bar');
    $this->assertSame('object', $objectType->jsonType());

    $enumType = new TypeDetails('enum', 'Foo\Bar', null, 'int', [1, 2, 3]);
    $this->assertSame('integer', $enumType->jsonType());

    $collectionType = new TypeDetails('collection', null, new TypeDetails('bool'));
    $this->assertSame('array', $collectionType->jsonType());

    $arrayType = new TypeDetails('array', null, null);
    $this->assertSame('array', $arrayType->jsonType());

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Unsupported type: unknown');
    $unknownType = new TypeDetails('unknown');
    $unknownType->jsonType();
});

test('returns correct short name', function () {
    $stringType = new TypeDetails('string');
    $this->assertSame('string', $stringType->shortName());

    $objectType = new TypeDetails('object', 'Foo\Bar');
    $this->assertSame('Bar', $objectType->shortName());

    $enumType = new TypeDetails('enum', 'Foo\Bar', null, 'string', ['foo', 'bar']);
    $this->assertSame('one of: foo, bar', $enumType->shortName());

    $collectionType = new TypeDetails('collection', null, new TypeDetails('int'));
    $this->assertSame('int[]', $collectionType->shortName());

    $arrayType = new TypeDetails('array', null, new TypeDetails('int'));
    $this->assertSame('array', $arrayType->shortName());
});

test('returns correct class only name', function () {
    $objectType = new TypeDetails('object', 'Foo\Bar');
    $this->assertSame('Bar', $objectType->classOnly());

    $enumType = new TypeDetails('enum', 'Foo\Bar', null, 'string', ['foo', 'bar']);
    $this->assertSame('Bar', $enumType->classOnly());

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Trying to get class name for type that is not an object or enum');
    $stringType = new TypeDetails('string');
    $stringType->classOnly();
});

test('converts JSON type to PHP type', function () {
    $this->assertSame('object', TypeDetails::toPhpType('object'));
    $this->assertSame('array', TypeDetails::toPhpType('array'));
    $this->assertSame('int', TypeDetails::toPhpType('integer'));
    $this->assertSame('float', TypeDetails::toPhpType('number'));
    $this->assertSame('string', TypeDetails::toPhpType('string'));
    $this->assertSame('bool', TypeDetails::toPhpType('boolean'));

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Unknown type: unknown');
    TypeDetails::toPhpType('unknown');
});