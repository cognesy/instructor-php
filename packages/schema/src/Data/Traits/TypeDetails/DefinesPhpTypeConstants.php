<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Traits\TypeDetails;

trait DefinesPhpTypeConstants
{
    public const PHP_MIXED = 'mixed';
    public const PHP_OBJECT = 'object';
    public const PHP_ENUM = 'enum';
    public const PHP_COLLECTION = 'collection';
    public const PHP_ARRAY = 'array';
    public const PHP_SHAPE = 'shape';
    public const PHP_INT = 'int';
    public const PHP_FLOAT = 'float';
    public const PHP_STRING = 'string';
    public const PHP_BOOL = 'bool';
    public const PHP_NULL = 'null';
    public const PHP_UNSUPPORTED = 'unsupported';

    public const PHP_TYPES = [
        self::PHP_OBJECT,
        self::PHP_ENUM,
        self::PHP_COLLECTION,
        self::PHP_ARRAY,
        self::PHP_INT,
        self::PHP_FLOAT,
        self::PHP_STRING,
        self::PHP_BOOL,
        self::PHP_MIXED,
    ];

    public const PHP_SCALAR_TYPES = [
        self::PHP_INT,
        self::PHP_FLOAT,
        self::PHP_STRING,
        self::PHP_BOOL,
        //self::PHP_NULL,
    ];

    public const PHP_OBJECT_TYPES = [
        self::PHP_OBJECT,
        self::PHP_ENUM,
    ];

    public const PHP_NON_SCALAR_TYPES = [
        self::PHP_OBJECT,
        self::PHP_ENUM,
        self::PHP_COLLECTION,
        self::PHP_ARRAY,
        self::PHP_SHAPE,
    ];

    public const PHP_ENUM_TYPES = [
        self::PHP_INT,
        self::PHP_STRING,
    ];

    private const TYPE_MAP = [
        "boolean" => self::PHP_BOOL,
        "integer" => self::PHP_INT,
        "double" => self::PHP_FLOAT,
        "string" => self::PHP_STRING,
        "array" => self::PHP_ARRAY,
        "object" => self::PHP_OBJECT,
        "resource" => self::PHP_UNSUPPORTED,
        "NULL" => self::PHP_UNSUPPORTED,
        "unknown type" => self::PHP_UNSUPPORTED,
        "resource (closed)" => self::PHP_UNSUPPORTED,
    ];
}