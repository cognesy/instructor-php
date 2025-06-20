<?php

namespace Cognesy\Utils\JsonSchema\Traits;

trait DefinesJsonTypes
{
    public const JSON_OBJECT = 'object';
    public const JSON_ARRAY = 'array';
    public const JSON_INTEGER = 'integer';
    public const JSON_NUMBER = 'number';
    public const JSON_STRING = 'string';
    public const JSON_BOOLEAN = 'boolean';
    public const JSON_ENUM = 'enum';
    public const JSON_NULL = 'null';
    public const JSON_ANY = 'any';

    public const JSON_TYPES = [
        self::JSON_OBJECT,
        self::JSON_ARRAY,
        self::JSON_INTEGER,
        self::JSON_NUMBER,
        self::JSON_STRING,
        self::JSON_BOOLEAN,
        self::JSON_ENUM,
    ];

    public const JSON_SCALAR_TYPES = [
        self::JSON_INTEGER,
        self::JSON_NUMBER,
        self::JSON_STRING,
        self::JSON_BOOLEAN,
    ];
}