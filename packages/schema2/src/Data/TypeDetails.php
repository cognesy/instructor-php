<?php declare(strict_types=1);

namespace Cognesy\Schema\Data;

use Cognesy\Schema\Exceptions\SchemaParsingException;
use Cognesy\Schema\Exceptions\TypeResolutionException;
use Cognesy\Schema\Factories\TypeDetailsFactory;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

final class TypeDetails
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
        'boolean' => self::PHP_BOOL,
        'integer' => self::PHP_INT,
        'double' => self::PHP_FLOAT,
        'string' => self::PHP_STRING,
        'array' => self::PHP_ARRAY,
        'object' => self::PHP_OBJECT,
        'resource' => self::PHP_UNSUPPORTED,
        'NULL' => self::PHP_UNSUPPORTED,
        'unknown type' => self::PHP_UNSUPPORTED,
        'resource (closed)' => self::PHP_UNSUPPORTED,
    ];

    /**
     * @param class-string|null $class
     * @param array<string|int|float|bool>|null $enumValues
     */
    public function __construct(
        public string $type,
        public ?string $class = null,
        public ?TypeDetails $nestedType = null,
        public ?string $enumType = null,
        public ?array $enumValues = null,
        public ?string $docString = null,
    ) {
        $this->validate($type, $class, $nestedType, $enumType, $enumValues);
    }

    public function type() : string {
        return $this->type;
    }

    public function class() : ?string {
        return $this->class;
    }

    public function nestedType() : ?TypeDetails {
        return $this->nestedType;
    }

    public function enumType() : ?string {
        return $this->enumType;
    }

    /**
     * @return array<string|int|float|bool>|null
     */
    public function enumValues() : ?array {
        return $this->enumValues;
    }

    public function docString() : string {
        return $this->docString ?? '';
    }

    public function isScalar() : bool {
        return in_array($this->type, self::PHP_SCALAR_TYPES, true);
    }

    public function isMixed() : bool {
        return $this->type === self::PHP_MIXED;
    }

    public function isInt() : bool {
        return $this->type === self::PHP_INT;
    }

    public function isString() : bool {
        return $this->type === self::PHP_STRING;
    }

    public function isBool() : bool {
        return $this->type === self::PHP_BOOL;
    }

    public function isFloat() : bool {
        return $this->type === self::PHP_FLOAT;
    }

    public function isObject() : bool {
        return $this->type === self::PHP_OBJECT;
    }

    public function isEnum() : bool {
        return $this->type === self::PHP_ENUM;
    }

    public function isArray() : bool {
        return in_array($this->type, [self::PHP_ARRAY, self::PHP_COLLECTION], true);
    }

    public function isCollection() : bool {
        return $this->type === self::PHP_COLLECTION;
    }

    public function isCollectionOf(string $type) : bool {
        return $this->isCollection() && $this->nestedType?->type() === $type;
    }

    public function isCollectionOfScalar() : bool {
        return $this->isCollection() && ($this->nestedType?->isScalar() ?? false);
    }

    public function isCollectionOfObject() : bool {
        return $this->isCollection() && ($this->nestedType?->isObject() ?? false);
    }

    public function isCollectionOfEnum() : bool {
        return $this->isCollection() && ($this->nestedType?->isEnum() ?? false);
    }

    public function isCollectionOfArray() : bool {
        return $this->isCollection() && ($this->nestedType?->isArray() ?? false);
    }

    public function hasNestedType() : bool {
        return $this->nestedType !== null;
    }

    public function hasClass() : bool {
        return $this->class !== null;
    }

    public function hasEnumType() : bool {
        return $this->enumType !== null;
    }

    public function toString() : string {
        return match ($this->type) {
            self::PHP_OBJECT => $this->class ?? self::PHP_OBJECT,
            self::PHP_ENUM => $this->class ?? self::PHP_ENUM,
            self::PHP_COLLECTION => ($this->nestedType?->__toString() ?? self::PHP_MIXED) . '[]',
            self::PHP_ARRAY => self::PHP_ARRAY,
            self::PHP_SHAPE => $this->docString ?? self::PHP_SHAPE,
            default => $this->type,
        };
    }

    public function __toString() : string {
        return $this->toString();
    }

    public function shortName() : string {
        return match ($this->type) {
            self::PHP_OBJECT => $this->classOnly(),
            self::PHP_ENUM => 'one of: ' . implode(', ', $this->enumValues ?? []),
            self::PHP_COLLECTION => ($this->nestedType?->shortName() ?? self::PHP_MIXED) . '[]',
            self::PHP_ARRAY => self::PHP_ARRAY,
            self::PHP_SHAPE => 'struct: ' . ($this->docString ?? ''),
            default => $this->type,
        };
    }

    public function classOnly() : string {
        if (!in_array($this->type, [self::PHP_OBJECT, self::PHP_ENUM], true)) {
            throw TypeResolutionException::unsupportedType($this->type);
        }
        $segments = explode('\\', $this->class ?? '');
        return (string) array_pop($segments);
    }

    public function toJsonType() : JsonSchemaType {
        return match ($this->type) {
            self::PHP_OBJECT => JsonSchemaType::object(),
            self::PHP_ENUM => $this->enumType === self::PHP_INT ? JsonSchemaType::integer() : JsonSchemaType::string(),
            self::PHP_COLLECTION, self::PHP_ARRAY => JsonSchemaType::array(),
            self::PHP_INT => JsonSchemaType::integer(),
            self::PHP_FLOAT => JsonSchemaType::number(),
            self::PHP_STRING => JsonSchemaType::string(),
            self::PHP_BOOL => JsonSchemaType::boolean(),
            default => throw TypeResolutionException::unsupportedType($this->type),
        };
    }

    public static function jsonToPhpType(JsonSchemaType $jsonType) : string {
        return match (true) {
            $jsonType->isObject() => self::PHP_OBJECT,
            $jsonType->isArray() => self::PHP_ARRAY,
            $jsonType->isInteger() => self::PHP_INT,
            $jsonType->isNumber() => self::PHP_FLOAT,
            $jsonType->isString() => self::PHP_STRING,
            $jsonType->isBoolean() => self::PHP_BOOL,
            default => throw TypeResolutionException::unsupportedType($jsonType->toString()),
        };
    }

    public static function fromJson(JsonSchema $json) : TypeDetails {
        return match (true) {
            $json->isOption() => self::option($json->enumValues()),
            $json->isObject() && !$json->hasObjectClass() => self::array(),
            $json->isObject() => (function () use ($json) : TypeDetails {
                /** @var class-string $objectClass */
                $objectClass = $json->objectClass() ?? throw TypeResolutionException::missingObjectClass($json->toString());
                return self::object($objectClass);
            })(),
            $json->isEnum() => (function () use ($json) : TypeDetails {
                /** @var class-string $enumClass */
                $enumClass = $json->objectClass() ?? throw TypeResolutionException::missingEnumClass($json->toString());
                return self::enum($enumClass, self::jsonToPhpType($json->type()), $json->enumValues());
            })(),
            $json->isCollection() => self::collection(match (true) {
                $json->itemSchema()?->isOption() => self::PHP_STRING,
                $json->itemSchema()?->isEnum() => $json->itemSchema()?->objectClass() ?? throw TypeResolutionException::missingEnumClass($json->toString()),
                ($json->itemSchema()?->isObject() === true) && !($json->itemSchema()?->hasObjectClass() ?? false) => self::PHP_ARRAY,
                $json->itemSchema()?->isObject() => $json->itemSchema()?->objectClass() ?? throw TypeResolutionException::missingObjectClass($json->toString()),
                $json->itemSchema()?->isScalar() => self::jsonToPhpType($json->itemSchema()?->type() ?? JsonSchemaType::any()),
                $json->itemSchema()?->isAny() => self::PHP_MIXED,
                default => throw SchemaParsingException::forMissingCollectionItems(),
            }),
            $json->isArray() => self::array(),
            $json->isString() => self::string(),
            $json->isBoolean() => self::bool(),
            $json->isInteger() => self::int(),
            $json->isNumber() => self::float(),
            $json->isAny() => self::mixed(),
            default => throw TypeResolutionException::unsupportedType($json->type()->toString()),
        };
    }

    /**
     * @param class-string $class
     */
    public static function object(string $class) : TypeDetails {
        return (new TypeDetailsFactory())->objectType($class);
    }

    /**
     * @param class-string $class
     * @param array<string|int|float|bool>|null $values
     */
    public static function enum(string $class, ?string $backingType = null, ?array $values = null) : TypeDetails {
        return (new TypeDetailsFactory())->enumType($class, $backingType, $values);
    }

    /**
     * @param array<string|int|float|bool> $values
     */
    public static function option(array $values) : TypeDetails {
        return (new TypeDetailsFactory())->optionType($values);
    }

    public static function collection(string $itemType) : TypeDetails {
        return (new TypeDetailsFactory())->collectionType($itemType);
    }

    public static function array() : TypeDetails {
        return (new TypeDetailsFactory())->arrayType();
    }

    public static function int() : TypeDetails {
        return (new TypeDetailsFactory())->scalarType(self::PHP_INT);
    }

    public static function string() : TypeDetails {
        return (new TypeDetailsFactory())->scalarType(self::PHP_STRING);
    }

    public static function bool() : TypeDetails {
        return (new TypeDetailsFactory())->scalarType(self::PHP_BOOL);
    }

    public static function float() : TypeDetails {
        return (new TypeDetailsFactory())->scalarType(self::PHP_FLOAT);
    }

    public static function mixed() : TypeDetails {
        return (new TypeDetailsFactory())->mixedType();
    }

    public static function fromTypeName(string $type) : TypeDetails {
        return (new TypeDetailsFactory())->fromTypeName($type);
    }

    public static function fromPhpDocTypeString(string $typeString) : TypeDetails {
        return (new TypeDetailsFactory())->fromPhpDocTypeString($typeString);
    }

    public static function fromValue(mixed $value) : TypeDetails {
        return (new TypeDetailsFactory())->fromValue($value);
    }

    public static function scalar(string $type) : TypeDetails {
        return (new TypeDetailsFactory())->scalarType($type);
    }

    public static function undefined() : TypeDetails {
        return new self(self::PHP_UNSUPPORTED);
    }

    /**
     * @return array{type:string,class:?string,nestedType:?array,enumType:?string,enumValues:?array,docString:?string}
     */
    public function toArray() : array {
        return [
            'type' => $this->type,
            'class' => $this->class,
            'nestedType' => $this->nestedType?->toArray(),
            'enumType' => $this->enumType,
            'enumValues' => $this->enumValues,
            'docString' => $this->docString,
        ];
    }

    public function clone() : self {
        return new self(
            type: $this->type,
            class: $this->class,
            nestedType: $this->nestedType?->clone(),
            enumType: $this->enumType,
            enumValues: $this->enumValues,
            docString: $this->docString,
        );
    }

    public static function getPhpType(mixed $variable) : ?string {
        $type = gettype($variable);
        return self::TYPE_MAP[$type] ?? self::PHP_UNSUPPORTED;
    }

    private function validate(
        string $type,
        ?string $class,
        ?TypeDetails $nestedType,
        ?string $enumType,
        ?array $enumValues,
    ) : void {
        if (!in_array($type, self::PHP_TYPES, true)) {
            throw TypeResolutionException::unsupportedType($type);
        }

        if ($type === self::PHP_ENUM) {
            if ($class === null) {
                throw TypeResolutionException::missingEnumClass($type);
            }
            if ($enumType === null) {
                throw TypeResolutionException::unsupportedType('enum:missing-backing-type');
            }
            if ($enumValues === null) {
                throw TypeResolutionException::unsupportedType('enum:missing-values');
            }
        }

        if ($type === self::PHP_COLLECTION && $nestedType === null) {
            throw TypeResolutionException::unsupportedType('collection:missing-nested-type');
        }
    }
}
