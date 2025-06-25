<?php

namespace Cognesy\Utils\JsonSchema\Traits\JsonSchemaType;

use Cognesy\Utils\JsonSchema\JsonSchemaType;

trait HandlesFactoryMethods
{
    public static function string() : self {
        return new self([JsonSchemaType::JSON_STRING]);
    }

    public static function integer() : self {
        return new self([JsonSchemaType::JSON_INTEGER]);
    }

    public static function boolean() : self {
        return new self([JsonSchemaType::JSON_BOOLEAN]);
    }

    public static function number() : self {
        return new self([JsonSchemaType::JSON_NUMBER]);
    }

    public static function object() : self {
        return new self([JsonSchemaType::JSON_OBJECT]);
    }

    public static function array() : self {
        return new self([JsonSchemaType::JSON_ARRAY]);
    }

    public static function any() : self {
        return new self([]);
    }
}