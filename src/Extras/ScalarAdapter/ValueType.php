<?php
namespace Cognesy\Instructor\Extras\ScalarAdapter;

enum ValueType : string
{
    case STRING = 'string';
    case INTEGER = 'int';
    case FLOAT = 'float';
    case BOOLEAN = 'bool';

    public function toJsonType() : string
    {
        return match ($this) {
            ValueType::STRING => 'string',
            ValueType::INTEGER => 'integer',
            ValueType::FLOAT => 'number',
            ValueType::BOOLEAN => 'boolean',
        };
    }
}