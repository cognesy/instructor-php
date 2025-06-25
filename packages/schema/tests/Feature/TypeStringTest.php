<?php

use Cognesy\Schema\TypeString\TypeString;

class TestUser {}
class TestProduct {}

enum TestStatus: string {
    case PENDING = 'pending';
    case COMPLETED = 'completed';
}

// Test type detection for scalar types
describe('TypeString: isScalar()', function () {
    test('returns true for a single scalar type', function (string $type) {
        expect(TypeString::fromString($type)->isScalar())->toBeTrue();
    })->with(['int', 'string', 'float', 'bool']);

    test('returns true for a union of scalar types', function () {
        expect(TypeString::fromString('int|string|bool')->isScalar())->toBeTrue();
    });

    test('returns true for a nullable scalar type', function () {
        expect(TypeString::fromString('?int')->isScalar())->toBeTrue();
    });

    test('returns false for non-scalar types', function (string $type) {
        expect(TypeString::fromString($type)->isScalar())->toBeFalse();
    })->with(['array', 'object', 'mixed', TestUser::class, 'int[]']);

    test('returns false for a union containing a non-scalar type', function () {
        expect(TypeString::fromString('int|array')->isScalar())->toBeFalse();
    });

    test('returns false for only null', function() {
        expect(TypeString::fromString('null')->isScalar())->toBeFalse();
    });
});

// Test type detection for array type
describe('TypeString: isArray()', function () {
    test('returns true for "array"', function () {
        expect(TypeString::fromString('array')->isArray())->toBeTrue();
    });

    test('returns true for "array|null"', function () {
        expect(TypeString::fromString('?array')->isArray())->toBeTrue();
    });

    test('returns false for a collection type like "int[]"', function () {
        expect(TypeString::fromString('int[]')->isArray())->toBeFalse();
    });

    test('returns false for a union of "array" and another type', function () {
        expect(TypeString::fromString('array|int')->isArray())->toBeFalse();
    });

    test('returns false for other types', function (string $type) {
        expect(TypeString::fromString($type)->isArray())->toBeFalse();
    })->with(['int', 'string', 'object', TestUser::class]);
});

// Test type detection for collection types (e.g., int[], string[])
describe('TypeString: isCollection() and getItemType()', function () {
    test('returns true for a simple collection', function (string $type) {
        expect(TypeString::fromString($type)->isCollection())->toBeTrue();
    })->with(['int[]', 'string[]', TestUser::class . '[]']);

    test('returns true for a union of collection types', function () {
        expect(TypeString::fromString('int[]|string[]')->isCollection())->toBeTrue();
    });

    test('returns true for a nullable collection', function () {
        expect(TypeString::fromString('?int[]')->isCollection())->toBeTrue();
    });

    test('returns false for non-collection types', function (string $type) {
        expect(TypeString::fromString($type)->isCollection())->toBeFalse();
    })->with(['int', 'string', 'array', 'object']);

    test('correctly gets item type for simple collection', function () {
        expect(TypeString::fromString('string[]')->itemType())->toBe('string');
        expect(TypeString::fromString(TestUser::class . '[]')->itemType())->toBe(TestUser::class);
    });

    test('correctly gets item type for union of collections', function () {
        // Since it returns the *first* type, and they are sorted, 'int' comes before 'string'.
        $typeString = TypeString::fromString('string[]|int[]');
        expect($typeString->isCollection())->toBeTrue();
        expect($typeString->itemType())->toBe('int');
    });

    test('correctly gets item type for nullable collection', function () {
        expect(TypeString::fromString('?int[]')->itemType())->toBe('int');
    });

    test('throws exception when getting item type from non-collection', function() {
        TypeString::fromString('int|string')->itemType();
    })->throws(\Exception::class);
});

// Test type detection for object types
describe('TypeString: isObject()', function () {
    test('returns true for a class name', function () {
        expect(TypeString::fromString(TestUser::class)->isObject())->toBeTrue();
    });

    test('returns true for "object"', function () {
        expect(TypeString::fromString('object')->isObject())->toBeTrue();
    });

    test('returns true for a union of object types', function () {
        expect(TypeString::fromString(TestUser::class . '|' . TestProduct::class)->isObject())->toBeTrue();
    });

    test('returns true for a nullable object', function () {
        expect(TypeString::fromString('?' . TestUser::class)->isObject())->toBeTrue();
    });

    test('returns false for scalar types', function (string $type) {
        expect(TypeString::fromString($type)->isObject())->toBeFalse();
    })->with(['int', 'string', 'bool']);

    test('returns false for array and collection types', function (string $type) {
        expect(TypeString::fromString($type)->isObject())->toBeFalse();
    })->with(['array', 'int[]', 'string[]']);
});

// Test type detection for enum object types
describe('TypeString: isEnumObject()', function () {
    test('returns true for a backed enum class', function () {
        expect(TypeString::fromString(TestStatus::class)->isEnumObject())->toBeTrue();
    });

    test('returns true for a nullable backed enum class', function () {
        expect(TypeString::fromString('?' . TestStatus::class)->isEnumObject())->toBeTrue();
    });

    test('returns false for a non-enum class', function () {
        expect(TypeString::fromString(TestUser::class)->isEnumObject())->toBeFalse();
    });

    test('returns false for scalar and array types', function (string $type) {
        expect(TypeString::fromString($type)->isEnumObject())->toBeFalse();
    })->with(['string', 'int', 'array', 'string[]']);
});

// Test general helper methods
describe('TypeString: General Methods', function () {
    test('isNullable() returns true for nullable types', function (string $type) {
        expect(TypeString::fromString($type)->isNullable())->toBeTrue();
    })->with(['?int', 'string|null', '?'.TestUser::class]);

    test('isNullable() returns false for non-nullable types', function () {
        expect(TypeString::fromString('int|string')->isNullable())->toBeFalse();
    });

    test('types() returns the correct sorted, unique array of types', function () {
        $typeString = TypeString::fromString('string|?int|string');
        expect($typeString->types())->toBe(['int', 'null', 'string']);
    });

    test('firstType() returns the first non-null type', function() {
        expect(TypeString::fromString('string|int')->firstType())->toBe('int'); // sorted
        expect(TypeString::fromString('?string')->firstType())->toBe('string');
        expect(TypeString::fromString(TestProduct::class.'|'.TestUser::class)->firstType())->toBe(TestProduct::class); // sorted
    });

    test('firstType() returns "mixed" for empty type string', function() {
        expect(TypeString::fromString('')->firstType())->toBe('mixed');
    });

    test('firstType() returns "null" for a "null" type string', function() {
        expect(TypeString::fromString('null')->firstType())->toBe('mixed');
    });

    test('toString() returns the normalized type string', function() {
        $ts = TypeString::fromString(' bool | ?string | string ');
        expect((string) $ts)->toBe('bool|null|string');
    });
});
