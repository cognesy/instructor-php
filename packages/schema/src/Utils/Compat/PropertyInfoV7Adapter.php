<?php declare(strict_types=1);

namespace Cognesy\Schema\Utils\Compat;

use Cognesy\Schema\Contracts\CanGetPropertyType;
use Cognesy\Schema\Data\TypeDetails;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

class PropertyInfoV7Adapter implements CanGetPropertyType
{
    private string $class;
    private string $propertyName;

    public function __construct(
        string $class,
        string $propertyName,
    ) {
        // if class name starts with ?, remove it
        if (str_starts_with($class, '?')) {
            $class = substr($class, 1);
        }
        $this->class = $class;
        $this->propertyName = $propertyName;
    }

    #[\Override]
    public function getPropertyTypeDetails(): TypeDetails {
        $types = $this->makeTypes();
        $typeString = $this->typeToString($types);
        return TypeDetails::fromPhpDocTypeString($typeString);
    }

    #[\Override]
    public function isPropertyNullable(): bool {
        return $this->makeTypes()->isNullable();
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function typeToString(Type $type): string {
        $types = [];
        if ($type->isIdentifiedBy(TypeIdentifier::INT)) { $types[] = 'int'; }
        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT)) { $types[] = 'float'; }
        if ($type->isIdentifiedBy(TypeIdentifier::STRING)) { $types[] = 'string'; }
        if ($type->isIdentifiedBy(TypeIdentifier::BOOL)) { $types[] = 'bool'; }
        if ($type->isIdentifiedBy(TypeIdentifier::ARRAY)) { $types[] = $this->getCollectionOrArrayType($type); }
        if ($type->isIdentifiedBy(TypeIdentifier::OBJECT)) {
            $unwrappedType = $this->unwrapType($type);
            /** @psalm-suppress UndefinedMethod - getClassName() exists on ObjectType */
            $types[] = $unwrappedType->getClassName();
        }
        if ($type->isIdentifiedBy(TypeIdentifier::ITERABLE)) { $types[] = $this->getCollectionOrArrayType($type); }
        //if ($type->isIdentifiedBy(TypeIdentifier::NULL)) { $types[] = 'null'; }
        if (empty($types)) {
            $types[] = 'mixed';
        }
        return implode('|', $types);
    }

    private function getCollectionOrArrayType(Type $type): string {
        $valueType = $this->resolveCollectionValueType($type);
        if ($valueType === null) {
            return 'array';
        }
        return $this->arrayTypeToString($valueType);
    }

    private function unwrapType(Type $type): Type {
        // Nullable/Wrapper unwrapping for >=7.2
        if (method_exists(Type::class, 'getBaseType')) {
            $base = $type->getBaseType();
            if ($base instanceof Type && $base !== $type) {
                return $base;
            }
        } else {
            // <7.2: unwrap nested wrappers
            if ($type instanceof WrappingTypeInterface) {
                return $type->getWrappedType();
            }
        }
        return $type;
    }

    private function resolveCollectionValueType(Type $type): ?Type {
        // Direct collection
        if ($type instanceof CollectionType) {
            return $type->getCollectionValueType();
        }
        // Nullable/Wrapper unwrapping for >=7.2
        if (method_exists(Type::class, 'getBaseType')) {
            $base = $type->getBaseType();
            if ($base instanceof Type && $base !== $type) {
                $resolved = $this->resolveCollectionValueType($base);
                if ($resolved instanceof Type) {
                    return $resolved;
                }
            }
        } else {
            // <7.2: unwrap nested wrappers
            while ($type instanceof WrappingTypeInterface) {
                $type = $type->getWrappedType();
                if ($type instanceof CollectionType) {
                    return $type->getCollectionValueType();
                }
            }
        }
        // Union: return first resolvable element type
        if ($type instanceof UnionType) {
            foreach ($type->getTypes() as $t) {
                $resolved = $this->resolveCollectionValueType($t);
                if ($resolved instanceof Type) {
                    return $resolved;
                }
            }
        }
        return null;
    }

    private function arrayTypeToString(Type $type) : string {
        $types = [];
        if ($type->isIdentifiedBy(TypeIdentifier::INT)) { $types[] = 'int[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT)) { $types[] = 'float[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::STRING)) { $types[] = 'string[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::BOOL)) { $types[] = 'bool[]'; }
        if ($type->isIdentifiedBy(TypeIdentifier::ARRAY)) { $types[] = 'array'; }
        if ($type->isIdentifiedBy(TypeIdentifier::OBJECT)) {
            $unwrappedType = $this->unwrapType($type);
            /** @psalm-suppress UndefinedMethod - getClassName() exists on ObjectType */
            $types[] = $unwrappedType->getClassName(). '[]';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::ITERABLE)) { $types[] = 'array'; }
        //if ($type->isIdentifiedBy(TypeIdentifier::NULL)) { $types[] = 'null'; }
        if (empty($types)) {
            $types[] = 'array';
        }
        return implode('|', $types);
    }

    private function makeTypes() : Type {
        $type = $this->makeExtractor()->getType($this->class, $this->propertyName);
        if (is_null($type)) {
            $type = Type::mixed();
        }
        return $type;
    }

    private function makeExtractor() : PropertyInfoExtractor {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        return new PropertyInfoExtractor(
            [$reflectionExtractor],
            [new PhpStanExtractor(), $phpDocExtractor, $reflectionExtractor],
            [$phpDocExtractor],
            [$reflectionExtractor],
            [$reflectionExtractor]
        );
    }
}
