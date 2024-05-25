<?php
namespace Cognesy\Instructor\Schema\Data;

class TypeDetails
{
    public const JSON_OBJECT = 'object';
    public const JSON_ARRAY = 'array';
    public const JSON_INTEGER = 'integer';
    public const JSON_NUMBER = 'number';
    public const JSON_STRING = 'string';
    public const JSON_BOOLEAN = 'boolean';

    public const JSON_TYPES = [
        self::JSON_OBJECT,
        self::JSON_ARRAY,
        self::JSON_INTEGER,
        self::JSON_NUMBER,
        self::JSON_STRING,
        self::JSON_BOOLEAN,
    ];

    public const PHP_MIXED = 'mixed';
    public const PHP_OBJECT = 'object';
    public const PHP_ENUM = 'enum';
    public const PHP_ARRAY = 'array';
    public const PHP_INT = 'int';
    public const PHP_FLOAT = 'float';
    public const PHP_STRING = 'string';
    public const PHP_BOOL = 'bool';
    public const PHP_UNSUPPORTED = null;

    public const PHP_TYPES = [
        self::PHP_OBJECT,
        self::PHP_ENUM,
        self::PHP_ARRAY,
        self::PHP_INT,
        self::PHP_FLOAT,
        self::PHP_STRING,
        self::PHP_BOOL,
    ];

    public const PHP_SCALAR_TYPES = [
        self::PHP_INT,
        self::PHP_FLOAT,
        self::PHP_STRING,
        self::PHP_BOOL,
    ];

    public const PHP_OBJECT_TYPES = [
        self::PHP_OBJECT,
        self::PHP_ENUM,
    ];

    public const PHP_NON_SCALAR_TYPES = [
        self::PHP_OBJECT,
        self::PHP_ENUM,
        self::PHP_ARRAY,
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

    public const JSON_SCALAR_TYPES = [
        self::JSON_INTEGER,
        self::JSON_NUMBER,
        self::JSON_STRING,
        self::JSON_BOOLEAN,
    ];

    /**
     * @param string $type object, enum, array, int, string, bool, float
     * @param class-string|null $class for objects and enums OR null
     * @param TypeDetails|null $nestedType for arrays OR null
     * @param string|null $enumType for enums OR null
     * @param array|null $enumValues for enums OR null
     */
    public function __construct(
        public string $type,
        public ?string $class = null,
        public ?TypeDetails $nestedType = null,
        public ?string $enumType = null,
        public ?array $enumValues = null,
    ) {
        if (!in_array($type, self::PHP_TYPES)) {
            throw new \Exception('Unsupported type: '.$type);
        }

        // ...check enum
        if ($type === self::PHP_ENUM) {
            if ($class === null) {
                throw new \Exception('Enum type must have a class');
            }
            if ($enumType === null) {
                throw new \Exception('Enum type must have an enum type');
            }
            if ($enumValues === null) {
                throw new \Exception('Enum type must have enum values');
            }
        }
        // ...check array
        if (($type === self::PHP_ARRAY) && ($nestedType === null)) {
            throw new \Exception('Array type must have a nested type');
        }
    }

    static public function undefined() : self {
        return new self(self::PHP_UNSUPPORTED);
    }

    public function __toString() : string {
        return $this->toString();
    }

    public function toString() : string {
        return match ($this->type) {
            self::PHP_OBJECT => $this->class,
            self::PHP_ENUM => $this->class,
            self::PHP_ARRAY => $this->nestedType->__toString().'[]',
            default => $this->type,
        };
    }

    public function jsonType() : string {
        return match ($this->type) {
            self::PHP_OBJECT => self::JSON_OBJECT,
            self::PHP_ENUM => ($this->enumType === self::PHP_INT ? self::JSON_INTEGER : self::JSON_STRING),
            self::PHP_ARRAY => self::JSON_ARRAY,
            self::PHP_INT => self::JSON_INTEGER,
            self::PHP_FLOAT => self::JSON_NUMBER,
            self::PHP_STRING => self::JSON_STRING,
            self::PHP_BOOL => self::JSON_BOOLEAN,
            default => throw new \Exception('Type not supported: '.$this->type),
        };
    }

    public function shortName() : string {
        return match ($this->type) {
            self::PHP_OBJECT => $this->classOnly(),
            self::PHP_ENUM => "one of: ".implode(', ', $this->enumValues),
            self::PHP_ARRAY => $this->nestedType->shortName().'[]',
            default => $this->type,
        };
    }

    public function classOnly() : string {
        if (!in_array($this->type, [self::PHP_OBJECT, self::PHP_ENUM])) {
            throw new \Exception('Trying to get class name for type that is not an object or enum');
        }
        $segments = explode('\\', $this->class);
        return array_pop($segments);
    }

    static public function fromJsonType(string $jsonType) : string {
        return match ($jsonType) {
            self::JSON_OBJECT => self::PHP_OBJECT,
            self::JSON_ARRAY => self::PHP_ARRAY,
            self::JSON_INTEGER => self::PHP_INT,
            self::JSON_NUMBER => self::PHP_FLOAT,
            self::JSON_STRING => self::PHP_STRING,
            self::JSON_BOOLEAN => self::PHP_BOOL,
            default => throw new \Exception('Unknown type: '.$jsonType),
        };
    }

    static public function getType(mixed $variable) : ?string {
        $type = gettype($variable);
        return self::TYPE_MAP[$type] ?? self::PHP_UNSUPPORTED;
    }
}
