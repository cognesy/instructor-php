<?php declare(strict_types=1);

namespace Cognesy\Schema;

use Cognesy\Schema\Exceptions\TypeResolutionException;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;
use ReflectionEnum;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

final class TypeInfo
{
    private static ?TypeResolver $resolver = null;

    public static function fromTypeName(?string $typeName) : Type {
        if ($typeName === null || trim($typeName) === '') {
            return Type::mixed();
        }

        $resolved = self::resolver()->resolve($typeName);
        return self::normalize($resolved, throwOnUnsupportedUnion: true);
    }

    public static function fromValue(mixed $value) : Type {
        return self::normalize(Type::fromValue($value));
    }

    public static function fromJsonSchema(JsonSchema $json) : Type {
        if ($json->isEnum()) {
            $className = $json->objectClass();
            if ($className !== null && enum_exists($className)) {
                return Type::enum($className);
            }

            return self::typeFromScalarJsonType($json->type());
        }

        if ($json->isObject()) {
            $className = $json->objectClass();
            return match (true) {
                $className !== null && (class_exists($className) || interface_exists($className) || enum_exists($className)) => Type::object($className),
                default => Type::object(),
            };
        }

        if ($json->isCollection()) {
            $itemSchema = $json->itemSchema();
            if ($itemSchema === null) {
                return Type::array();
            }

            return Type::list(self::fromJsonSchema($itemSchema));
        }

        if ($json->isArray()) {
            return Type::array();
        }

        return self::typeFromScalarJsonType($json->type());
    }

    public static function normalize(Type $type, bool $throwOnUnsupportedUnion = false) : Type {
        if ($type instanceof NullableType) {
            return self::normalize($type->getWrappedType(), $throwOnUnsupportedUnion);
        }

        if (self::isBool($type)) {
            return Type::bool();
        }

        if (!$type instanceof UnionType) {
            return $type;
        }

        $branches = self::nonNullBranches($type);
        if ($branches === []) {
            return Type::mixed();
        }

        if (count($branches) === 1) {
            return self::normalize($branches[0], $throwOnUnsupportedUnion);
        }

        if (self::isNumericUnion($branches)) {
            return Type::float();
        }

        if (!self::allScalar($branches) && $throwOnUnsupportedUnion) {
            throw TypeResolutionException::unsupportedUnion((string) $type);
        }

        return Type::mixed();
    }

    public static function isScalar(Type $type) : bool {
        $type = self::normalize($type);

        return $type->isIdentifiedBy(TypeIdentifier::INT)
            || $type->isIdentifiedBy(TypeIdentifier::FLOAT)
            || $type->isIdentifiedBy(TypeIdentifier::STRING)
            || self::isBool($type);
    }

    public static function isBool(Type $type) : bool {
        return $type->isIdentifiedBy(TypeIdentifier::BOOL)
            || $type->isIdentifiedBy(TypeIdentifier::TRUE)
            || $type->isIdentifiedBy(TypeIdentifier::FALSE);
    }

    public static function isMixed(Type $type) : bool {
        return self::normalize($type)->isIdentifiedBy(TypeIdentifier::MIXED);
    }

    public static function isEnum(Type $type) : bool {
        return self::normalize($type) instanceof EnumType;
    }

    public static function isObject(Type $type) : bool {
        $normalized = self::normalize($type);
        if ($normalized instanceof CollectionType || $normalized instanceof EnumType) {
            return false;
        }

        return $normalized instanceof ObjectType
            || $normalized->isIdentifiedBy(TypeIdentifier::OBJECT);
    }

    public static function isArray(Type $type) : bool {
        $collectionValueType = self::collectionValueType($type);
        if ($collectionValueType === null) {
            return self::normalize($type)->isIdentifiedBy(TypeIdentifier::ARRAY);
        }

        return self::isMixed($collectionValueType);
    }

    public static function isCollection(Type $type) : bool {
        $collectionValueType = self::collectionValueType($type);
        if ($collectionValueType === null) {
            return false;
        }

        return !self::isMixed($collectionValueType);
    }

    public static function collectionValueType(Type $type) : ?Type {
        if ($type instanceof CollectionType) {
            return self::normalize($type->getCollectionValueType());
        }

        $baseType = self::baseType($type);
        if ($baseType !== $type) {
            $resolved = self::collectionValueType($baseType);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (!$type instanceof UnionType) {
            return null;
        }

        foreach ($type->getTypes() as $branch) {
            $resolved = self::collectionValueType($branch);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /** @return class-string|null */
    public static function className(Type $type) : ?string {
        foreach ($type->traverse() as $branch) {
            if (!$branch instanceof ObjectType) {
                continue;
            }

            $className = $branch->getClassName();
            if (!class_exists($className) && !interface_exists($className) && !enum_exists($className)) {
                continue;
            }

            /** @var class-string $className */
            return $className;
        }

        return null;
    }

    /**
     * @return array<string|int>
     */
    public static function enumValues(Type $type) : array {
        $className = self::className($type);
        if ($className === null || !enum_exists($className)) {
            return [];
        }

        $reflection = new ReflectionEnum($className);
        if (!$reflection->isBacked()) {
            return [];
        }

        return array_map(
            static fn(\ReflectionEnumBackedCase $case) : string|int => $case->getBackingValue(),
            $reflection->getCases(),
        );
    }

    public static function enumBackingType(Type $type) : ?string {
        $className = self::className($type);
        if ($className === null || !enum_exists($className)) {
            return null;
        }

        $reflection = new ReflectionEnum($className);
        if (!$reflection->isBacked()) {
            return null;
        }

        $backingType = $reflection->getBackingType();
        if (!$backingType instanceof \ReflectionNamedType) {
            return null;
        }

        return $backingType->getName();
    }

    public static function shortName(Type $type) : string {
        $className = self::className($type);
        if ($className !== null) {
            return basename(str_replace('\\', '/', $className));
        }

        return (string) self::normalize($type);
    }

    public static function toJsonType(Type $type) : JsonSchemaType {
        $type = self::normalize($type);
        if ($type instanceof CollectionType || $type->isIdentifiedBy(TypeIdentifier::ARRAY)) {
            return JsonSchemaType::array();
        }

        return match (true) {
            self::isBool($type) => JsonSchemaType::boolean(),
            $type->isIdentifiedBy(TypeIdentifier::INT) => JsonSchemaType::integer(),
            $type->isIdentifiedBy(TypeIdentifier::FLOAT) => JsonSchemaType::number(),
            $type->isIdentifiedBy(TypeIdentifier::STRING) => JsonSchemaType::string(),
            self::isObject($type) => JsonSchemaType::object(),
            default => JsonSchemaType::any(),
        };
    }

    public static function isDateTimeClass(Type $type) : bool {
        $className = self::className($type);
        if ($className === null) {
            return false;
        }

        return in_array($className, [\DateTime::class, \DateTimeImmutable::class], true);
    }

    public static function cacheKey(Type $type, ?array $enumValues = null) : string {
        $suffix = $enumValues === null ? '' : '|enum:' . implode(',', array_map(strval(...), $enumValues));
        return (string) self::normalize($type) . $suffix;
    }

    // INTERNALS ////////////////////////////////////////////////////////////////////

    private static function resolver() : TypeResolver {
        if (self::$resolver !== null) {
            return self::$resolver;
        }

        self::$resolver = TypeResolver::create();
        return self::$resolver;
    }

    /**
     * @return list<Type>
     */
    private static function nonNullBranches(UnionType $type) : array {
        $branches = [];
        foreach ($type->getTypes() as $branch) {
            $baseType = self::baseType($branch);
            if ($baseType->isIdentifiedBy(TypeIdentifier::NULL)) {
                continue;
            }
            $branches[] = $baseType;
        }

        return $branches;
    }

    /**
     * @param list<Type> $branches
     */
    private static function allScalar(array $branches) : bool {
        foreach ($branches as $branch) {
            if (self::isScalar($branch)) {
                continue;
            }
            return false;
        }

        return true;
    }

    /**
     * @param list<Type> $branches
     */
    private static function isNumericUnion(array $branches) : bool {
        $seen = [];
        foreach ($branches as $branch) {
            if ($branch->isIdentifiedBy(TypeIdentifier::INT)) {
                $seen['int'] = true;
                continue;
            }

            if ($branch->isIdentifiedBy(TypeIdentifier::FLOAT)) {
                $seen['float'] = true;
            }
        }

        return isset($seen['int'], $seen['float']) && count($seen) === 2;
    }

    private static function baseType(Type $type) : Type {
        $unwrapped = $type;
        while ($unwrapped instanceof WrappingTypeInterface && !$unwrapped instanceof CollectionType) {
            $next = $unwrapped->getWrappedType();
            if (!$next instanceof Type || $next === $unwrapped) {
                break;
            }
            $unwrapped = $next;
        }

        if ($unwrapped instanceof CollectionType) {
            return $unwrapped;
        }

        if (!method_exists(Type::class, 'getBaseType')) {
            return $unwrapped;
        }

        $baseType = $unwrapped->getBaseType();
        if (!$baseType instanceof Type || $baseType === $unwrapped) {
            return $unwrapped;
        }

        return $baseType;
    }

    private static function typeFromScalarJsonType(JsonSchemaType $jsonType) : Type {
        return match (true) {
            $jsonType->isInteger() => Type::int(),
            $jsonType->isNumber() => Type::float(),
            $jsonType->isBoolean() => Type::bool(),
            $jsonType->isString() => Type::string(),
            default => Type::mixed(),
        };
    }
}
