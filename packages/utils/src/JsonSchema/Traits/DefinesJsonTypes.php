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
    public const JSON_NULL = 'null';
    public const JSON_ANY_OF = ['anyOf' => [
        ['type' => self::JSON_OBJECT],
        ['type' => self::JSON_ARRAY],
        ['type' => self::JSON_INTEGER],
        ['type' => self::JSON_NUMBER],
        ['type' => self::JSON_STRING],
        ['type' => self::JSON_BOOLEAN],
    ]];
    public const JSON_ANY = 'any'; // no longer used

    public const JSON_TYPES = [
        self::JSON_OBJECT,
        self::JSON_ARRAY,
        self::JSON_INTEGER,
        self::JSON_NUMBER,
        self::JSON_STRING,
        self::JSON_BOOLEAN,
    ];

    const JSON_ANY_TYPES = [
        self::JSON_OBJECT,
        self::JSON_ARRAY,
        self::JSON_INTEGER,
        self::JSON_NUMBER,
        self::JSON_STRING,
        self::JSON_BOOLEAN,
    ];

    public const JSON_SCALAR_TYPES = [
        self::JSON_INTEGER,
        self::JSON_NUMBER,
        self::JSON_STRING,
        self::JSON_BOOLEAN,
    ];
}