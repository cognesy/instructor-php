<?php

use Cognesy\Schema\TypeString\TypeString;

// SECTION: Basic Type Parsing
it('parses scalar, class, and nullable types', function (string $type, array $expected) {
    expect(TypeString::fromString($type)->types())->toEqual($expected);
})->with([
    // Scalar types
    ['int', ['int']],
    ['string', ['string']],
    ['bool', ['bool']],
    ['float', ['float']],
    ['mixed', ['mixed']],
    //['void', ['void']],
    ['null', ['mixed']],

    // Class types
    ['MyClass', ['MyClass']],
    ['App\Model\User', ['App\Model\User']],
    ['\\Fully\\Qualified\\Class', ['\\Fully\\Qualified\\Class']],

    // Nullable types
    ['?int', ['int', 'null']],
    ['?string', ['null', 'string']],
    ['?MyClass', ['MyClass', 'null']],
    ['?int[]', ['int[]', 'null']],
]);

// SECTION: Array Type Parsing
it('parses and normalizes various array notations', function (string $type, array $expected) {
    expect(TypeString::fromString($type)->types())->toEqual($expected);
})->with([
    // Plain array
    ['array', ['array']],
    ['array[]', ['array']], // An array of 'array' type

    // Simple array notation
    ['int[]', ['int[]']],
    ['string[]', ['string[]']],
    ['MyClass[]', ['MyClass[]']],

    // Generic syntax
    ['array<int>', ['int[]']],
    ['array<MyClass>', ['MyClass[]']],
    ['array<\\App\\Model>', ['\\App\\Model[]']],

    // Keyed generic syntax (key type is ignored)
    ['array<int, bool>', ['bool[]']],
    ['array<string, MyClass>', ['MyClass[]']],

    // Nested arrays are always normalized to plain array
    ['array<array<int>>', ['int[][]']],
]);

// SECTION: Union Type Parsing
it('parses union types including complex and nested cases', function (string $type, array $expected) {
    expect(TypeString::fromString($type)->types())->toEqual($expected);
})->with([
    // Simple unions
    ['int|string', ['int', 'string']],
    ['bool|int|string', ['bool', 'int', 'string']],
    ['MyClass|OtherClass', ['MyClass', 'OtherClass']],

    // Unions with null and nullables
    ['int|null', ['int', 'null']],
    ['bool|?int', ['bool', 'int', 'null']],
    ['?int|?string', ['int', 'null', 'string']],

    // Unions with arrays
    ['string|int[]', ['int[]', 'string']],
    ['int[]|string[]', ['int[]', 'string[]']],
    ['int[]|bool[]|Collection', ['Collection', 'bool[]', 'int[]']],

    // Unions within generics
    ['array<bool|string>', ['bool[]', 'string[]']],
    ['array<int, bool|string>', ['bool[]', 'string[]']],
    ['array<int|bool|MyClass>', ['MyClass[]', 'bool[]', 'int[]']],

    // Complex combinations
    ['bool|array<bool|string|MyClass>', ['MyClass[]', 'bool', 'bool[]', 'string[]']],
    ['int[]|bool[]|array<Collection|int|bool>|string', ['Collection[]', 'bool[]', 'int[]', 'string']],

    // Unions that result in duplicates
    ['int[]|array<int>', ['int[]']],
    ['int[]|array<int>|bool[]', ['bool[]', 'int[]']],
    ['int[]|array<int|bool>|bool[]', ['bool[]', 'int[]']],
]);


// SECTION: Formatting and Edge Cases
it('handles various whitespace patterns', function (string $type, array $expected) {
    expect(TypeString::fromString($type)->types())->toEqual($expected);
})->with([
    [' int ', ['int']],
    ['? int', ['int', 'null']],
    [' ? int ', ['int', 'null']],
    ['int | string', ['int', 'string']],
    ['array< int , bool >', ['bool[]']],
    [' int[] | array< MyClass > ', ['MyClass[]', 'int[]']],
]);

it('handles edge cases and invalid syntax gracefully', function (string $type, array $expected) {
    expect(TypeString::fromString($type)->types())->toEqual($expected);
})->with([
    // Empty or whitespace
    ['', ['mixed']],
    [' ', ['mixed']],
    ['  ', ['mixed']],

    // Dangling operators
    ['|', ['mixed']],
    ['||', ['mixed']],
    ['int|', ['int']],
    ['|int', ['int']],
]);

it('throws exception for invalid syntax', function (string $type) {
    expect(fn() => TypeString::fromString($type)->types())->toThrow(\Exception::class);
})->with([
    ['array<'], // Incomplete generic
    ['?'], // Just a nullable without type
]);
