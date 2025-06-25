<?php

use Cognesy\Schema\TypeString\TypeStringParser;

// We need a parser instance for our tests
beforeEach(function () {
    $this->parser = new TypeStringParser();
});

// Test parsing of basic, built-in types
describe('Parser: Basic Types', function () {
    test('parses int', function () {
        expect($this->parser->getTypes('int'))->toBe(['int']);
    });

    test('parses string', function () {
        expect($this->parser->getTypes('string'))->toBe(['string']);
    });

    test('parses float', function () {
        expect($this->parser->getTypes('float'))->toBe(['float']);
    });

    test('parses bool', function () {
        expect($this->parser->getTypes('bool'))->toBe(['bool']);
    });

    test('parses array', function () {
        expect($this->parser->getTypes('array'))->toBe(['array']);
    });

    test('parses list', function () {
        expect($this->parser->getTypes('list'))->toBe(['list']);
    });

    test('parses mixed', function () {
        expect($this->parser->getTypes('mixed'))->toBe(['mixed']);
    });

    test('parses "any" as mixed', function () {
        expect($this->parser->getTypes('any'))->toBe(['any']);
    });

    test('parses null', function () {
        expect($this->parser->getTypes('null'))->toBe(['null']);
    });

    test('handles leading/trailing whitespace', function () {
        expect($this->parser->getTypes('  string  '))->toBe(['string']);
    });
});

// Test handling of empty or invalid type strings
describe('Parser: Empty and Invalid Types', function () {
    test('handles empty string', function () {
        expect($this->parser->getTypes(''))->toBe([]);
    });

    test('handles string with only whitespace', function () {
        expect($this->parser->getTypes('   '))->toBe([]);
    });

    test('handles single pipe', function () {
        expect($this->parser->getTypes('|'))->toBe([]);
    });

    test('handles double pipe', function () {
        expect($this->parser->getTypes('||'))->toBe([]);
    });

    test('throws exception for incomplete nullable type "?"', function () {
        $this->parser->getTypes('?');
    })->throws(InvalidArgumentException::class);

    test('throws exception for incomplete generic type "array<"', function () {
        $this->parser->getTypes('array<');
    })->throws(InvalidArgumentException::class);
});

// Test parsing of nullable types
describe('Parser: Nullable Types', function () {
    test('parses nullable int', function () {
        expect($this->parser->getTypes('?int'))->toBe(['int', 'null']);
    });

    test('parses nullable class', function () {
        expect($this->parser->getTypes('?App\MyClass'))->toBe(['App\MyClass', 'null']);
    });

    test('parses nullable array', function () {
        expect($this->parser->getTypes('?array<string>'))->toBe(['null', 'string[]']);
    });

    test('parses nullable type in a union', function () {
        expect($this->parser->getTypes('string|?int'))->toBe(['int', 'null', 'string']);
    });
});

// Test parsing of union types
describe('Parser: Union Types', function () {
    test('parses simple union', function () {
        expect($this->parser->getTypes('int|string'))->toBe(['int', 'string']);
    });

    test('parses union with whitespace', function () {
        expect($this->parser->getTypes(' int | string |bool '))->toBe(['bool', 'int', 'string']);
    });

    test('handles duplicate types in union and sorts them', function () {
        expect($this->parser->getTypes('string|int|string'))->toBe(['int', 'string']);
    });

    test('handles leading pipe', function () {
        expect($this->parser->getTypes('|int|string'))->toBe(['int', 'string']);
    });

    test('handles trailing pipe', function () {
        expect($this->parser->getTypes('int|string|'))->toBe(['int', 'string']);
    });

    test('parses union with nullable type', function () {
        expect($this->parser->getTypes('string|?int|float'))->toBe(['float', 'int', 'null', 'string']);
    });
});

// Test parsing of array and collection types
describe('Parser: Array and Collection Types', function () {
    // Array<T> syntax
    test('parses generic array', function () {
        expect($this->parser->getTypes('array<string>'))->toBe(['string[]']);
    });

    test('parses generic list', function () {
        expect($this->parser->getTypes('list<int>'))->toBe(['int[]']);
    });

    test('parses generic array with class type', function () {
        expect($this->parser->getTypes('array<App\Models\User>'))->toBe(['App\Models\User[]']);
    });

    // T[] syntax
    test('parses simple array syntax', function () {
        expect($this->parser->getTypes('int[]'))->toBe(['int[]']);
    });

    test('parses class array syntax', function () {
        expect($this->parser->getTypes('App\Models\User[]'))->toBe(['App\Models\User[]']);
    });

    // Complex cases
    test('parses union within generic array', function () {
        expect($this->parser->getTypes('array<int|string>'))->toBe(['int[]', 'string[]']);
    });

    test('parses union of arrays', function () {
        expect($this->parser->getTypes('int[]|string[]'))->toBe(['int[]', 'string[]']);
    });

    test('parses nullable item type in generic array', function () {
        expect($this->parser->getTypes('array<?string>'))->toBe(['null', 'string[]']);
    });

    test('parses array of nullable items and the array itself is nullable', function () {
        expect($this->parser->getTypes('?array<?int>'))->toBe(['int[]', 'null']);
    });

    test('parses complex nested generic', function () {
        expect($this->parser->getTypes('array<string, list<int|bool>>'))->toBe(['bool[][]', 'int[][]']);
    });

    test('parses union of generic array and simple type', function () {
        expect($this->parser->getTypes('string|array<int>'))->toBe(['int[]', 'string']);
    });

    test('parses union of simple array syntax and simple type', function () {
        expect($this->parser->getTypes('bool|int[]'))->toBe(['bool', 'int[]']);
    });

    test('parses "array[]" as "array"', function () {
        expect($this->parser->getTypes('array[]'))->toBe(['array']);
    });
});

// Test parsing of class and enum types
describe('Parser: Class and Enum Types', function () {
    test('parses a simple class name', function () {
        expect($this->parser->getTypes('User'))->toBe(['User']);
    });

    test('parses a fully qualified class name', function () {
        expect($this->parser->getTypes('\\App\\Models\\User'))->toBe(['\\App\\Models\\User']);
    });

    test('parses a union of classes', function () {
        expect($this->parser->getTypes('User|Product'))->toBe(['Product', 'User']);
    });

    test('parses an array of classes', function () {
        expect($this->parser->getTypes('User[]'))->toBe(['User[]']);
    });

    test('parses a generic array of classes', function () {
        expect($this->parser->getTypes('array<User>'))->toBe(['User[]']);
    });

    test('parses a nullable class', function () {
        expect($this->parser->getTypes('?User'))->toBe(['User', 'null']);
    });
});
