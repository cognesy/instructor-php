<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums;

use ReflectionType;

/**
 * Supported types:
 * - Any built-in type (php 8.2): array, bool, float, int, object, string
 * - Name of a class, enum
 * Unsupported types:
 * - callable, false, true, void, mixed, never, iterable, null
 * - any relative class type: parent, self, static
 * - trait names cannot be used for typing hints
 */
enum PhpType: string {
    case STRING = 'string';
    case INTEGER = 'int';
    case FLOAT = 'float';
    case BOOLEAN = 'bool';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case ENUM = 'enum';
    case NULL = 'null';
    case MIXED = 'mixed';
    case UNDEFINED = 'undefined';

    static public function fromReflectionType(ReflectionType $type): PhpType
    {
        if ($type === null) {
            return PhpType::UNDEFINED;
        }
        $typeName = $type->getName();
        switch ($typeName) {
            case 'string':
                return PhpType::STRING;
            case 'int':
                return PhpType::INTEGER;
            case 'float':
                return PhpType::FLOAT;
            case 'bool':
                return PhpType::BOOLEAN;
            case 'array':
            case 'iterable':
                return PhpType::ARRAY;
            case 'object':
                return PhpType::OBJECT;
            case 'enum':
                return PhpType::ENUM;
            case 'null':
                return PhpType::NULL;
            default:
                return PhpType::UNDEFINED;
        }
    }
}
