<?php
namespace Cognesy\Instructor\Reflection\Enums;

enum JsonType: string {
    case STRING = 'string';
    case INTEGER = 'integer';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case OBJECT = 'object';
    case ARRAY = 'array';
    case NULL = 'null';
    case UNDEFINED = 'undefined';

    static public function fromPhpType(PhpType $type): JsonType {
        switch ($type) {
            case PhpType::STRING:
                return JsonType::STRING;
            case PhpType::INTEGER:
                return JsonType::INTEGER;
            case PhpType::FLOAT:
                return JsonType::NUMBER;
            case PhpType::BOOLEAN:
                return JsonType::BOOLEAN;
            case PhpType::ARRAY:
                return JsonType::ARRAY;
            case PhpType::OBJECT:
                return JsonType::OBJECT;
            case PhpType::ENUM:
                return JsonType::STRING;
            case PhpType::NULL:
                return JsonType::NULL;
            default:
                return JsonType::UNDEFINED;
        }
    }
}