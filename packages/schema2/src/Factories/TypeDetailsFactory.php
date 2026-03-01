<?php declare(strict_types=1);

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Exceptions\TypeResolutionException;
use ReflectionEnum;

class TypeDetailsFactory
{
    public function fromPhpDocTypeString(string $typeSpec) : TypeDetails {
        if ($typeSpec === '') {
            throw TypeResolutionException::emptyTypeSpecification();
        }

        return $this->fromTypeName($typeSpec);
    }

    public function fromTypeName(?string $anyType) : TypeDetails {
        if ($anyType === null) {
            return $this->mixedType();
        }

        $tokens = $this->normalizeUnionTokens($anyType);
        if ($tokens === []) {
            return $this->mixedType();
        }

        $nonNullTokens = array_values(array_filter($tokens, static fn(string $token) : bool => $token !== TypeDetails::PHP_NULL));

        if ($nonNullTokens === []) {
            return $this->mixedType();
        }

        if (count($nonNullTokens) > 1) {
            return $this->resolveUnion($nonNullTokens, $anyType);
        }

        return $this->fromSingleToken($nonNullTokens[0], $anyType);
    }

    public function fromValue(mixed $anyVar) : TypeDetails {
        if (is_object($anyVar)) {
            $className = get_class($anyVar) ?: throw TypeResolutionException::missingObjectClass('object');
            return $anyVar instanceof \BackedEnum
                ? $this->enumType($className)
                : $this->objectType($className);
        }

        if (!is_array($anyVar)) {
            $typeName = TypeDetails::getPhpType($anyVar) ?? TypeDetails::PHP_MIXED;
            return in_array($typeName, TypeDetails::PHP_SCALAR_TYPES, true)
                ? $this->scalarType($typeName)
                : $this->mixedType();
        }

        if ($anyVar === []) {
            return $this->arrayType();
        }

        if (!$this->allItemsShareType($anyVar)) {
            return $this->arrayType();
        }

        return $this->collectionTypeFromValues($anyVar);
    }

    public function scalarType(string $type) : TypeDetails {
        if ($type === '') {
            return $this->mixedType();
        }

        if (!in_array($type, TypeDetails::PHP_SCALAR_TYPES, true)) {
            throw TypeResolutionException::unsupportedType($type);
        }

        return new TypeDetails(type: $type, docString: $type);
    }

    public function arrayType(string $typeSpec = '') : TypeDetails {
        return new TypeDetails(type: TypeDetails::PHP_ARRAY, docString: $typeSpec);
    }

    public function collectionType(string $typeSpec) : TypeDetails {
        $itemType = $this->collectionItemType($typeSpec);

        $nestedType = match (true) {
            $itemType === TypeDetails::PHP_MIXED => throw TypeResolutionException::unsupportedType('collection<mixed>'),
            $itemType === TypeDetails::PHP_ARRAY => throw TypeResolutionException::unsupportedType('collection<array>'),
            in_array($itemType, TypeDetails::PHP_SCALAR_TYPES, true) => $this->scalarType($itemType),
            default => $this->objectType($itemType),
        };

        return new TypeDetails(
            type: TypeDetails::PHP_COLLECTION,
            nestedType: $nestedType,
            docString: $typeSpec,
        );
    }

    /**
     * @param class-string $typeName
     */
    public function objectType(string $typeName) : TypeDetails {
        if (enum_exists($typeName)) {
            return $this->enumType($typeName);
        }

        if (!class_exists($typeName) && !interface_exists($typeName)) {
            throw TypeResolutionException::unsupportedType($typeName);
        }

        return new TypeDetails(
            type: TypeDetails::PHP_OBJECT,
            class: $typeName,
            docString: $typeName,
        );
    }

    /**
     * @param class-string $typeName
     * @param array<string|int|float|bool>|null $enumValues
     */
    public function enumType(string $typeName, ?string $enumType = null, ?array $enumValues = null) : TypeDetails {
        if (!enum_exists($typeName)) {
            throw TypeResolutionException::unsupportedType($typeName);
        }

        $reflection = new ReflectionEnum($typeName);
        if (!$reflection->isBacked()) {
            throw TypeResolutionException::unsupportedType('enum:not-backed');
        }

        $backingType = $enumType ?? $reflection->getBackingType()?->getName() ?? '';
        if (!in_array($backingType, TypeDetails::PHP_ENUM_TYPES, true)) {
            throw TypeResolutionException::unsupportedType('enum:' . $backingType);
        }

        $values = $enumValues ?? array_map(
            static fn(\ReflectionEnumBackedCase $case) : string|int => $case->getBackingValue(),
            $reflection->getCases(),
        );

        return new TypeDetails(
            type: TypeDetails::PHP_ENUM,
            class: $typeName,
            enumType: $backingType,
            enumValues: $values,
            docString: $typeName,
        );
    }

    /**
     * @param array<string|int|float|bool> $values
     */
    public function optionType(array $values) : TypeDetails {
        return new TypeDetails(type: TypeDetails::PHP_STRING, enumValues: $values);
    }

    public function mixedType() : TypeDetails {
        return new TypeDetails(type: TypeDetails::PHP_MIXED, docString: TypeDetails::PHP_MIXED);
    }

    /**
     * @return list<string>
     */
    private function normalizeUnionTokens(string $typeSpec) : array {
        $normalized = str_replace(' ', '', trim($typeSpec));
        if ($normalized === '') {
            return [];
        }

        if (str_starts_with($normalized, '?')) {
            $normalized = substr($normalized, 1) . '|' . TypeDetails::PHP_NULL;
        }

        $parts = array_values(array_filter(explode('|', $normalized), static fn(string $token) : bool => $token !== ''));
        if ($parts === []) {
            return [];
        }

        $parts = array_values(array_unique($parts));
        sort($parts);
        return $parts;
    }

    private function fromSingleToken(string $token, string $sourceType) : TypeDetails {
        if ($token === TypeDetails::PHP_OBJECT) {
            throw TypeResolutionException::missingObjectClass($sourceType);
        }

        if ($token === TypeDetails::PHP_ENUM) {
            throw TypeResolutionException::missingEnumClass($sourceType);
        }

        if ($token === TypeDetails::PHP_MIXED) {
            return $this->mixedType();
        }

        if ($token === TypeDetails::PHP_ARRAY) {
            return $this->arrayType();
        }

        if (in_array($token, TypeDetails::PHP_SCALAR_TYPES, true)) {
            return $this->scalarType($token);
        }

        if (str_ends_with($token, '[]')) {
            return $this->collectionType($token);
        }

        if (enum_exists($token)) {
            return $this->enumType($token);
        }

        if (class_exists($token) || interface_exists($token)) {
            return $this->objectType($token);
        }

        throw TypeResolutionException::unsupportedType($sourceType);
    }

    /**
     * @param list<string> $nonNullTypes
     */
    private function resolveUnion(array $nonNullTypes, string $sourceType) : TypeDetails {
        $allScalar = true;
        foreach ($nonNullTypes as $type) {
            if (!in_array($type, TypeDetails::PHP_SCALAR_TYPES, true)) {
                $allScalar = false;
                break;
            }
        }

        if (!$allScalar) {
            throw TypeResolutionException::unsupportedUnion($sourceType);
        }

        if ($nonNullTypes === [TypeDetails::PHP_FLOAT, TypeDetails::PHP_INT]) {
            return $this->scalarType(TypeDetails::PHP_FLOAT);
        }

        return $this->mixedType();
    }

    private function allItemsShareType(array $array) : bool {
        $type = null;
        foreach ($array as $item) {
            if ($item === null) {
                continue;
            }

            $itemType = TypeDetails::getPhpType($item);
            if ($itemType === TypeDetails::PHP_UNSUPPORTED) {
                return false;
            }

            if ($type === null) {
                $type = $itemType;
                continue;
            }

            if ($itemType !== $type) {
                return false;
            }
        }

        return $type !== null;
    }

    private function collectionTypeFromValues(array $array) : TypeDetails {
        $sample = $this->firstNonNullItem($array);
        if ($sample === null) {
            return $this->arrayType();
        }

        $nestedType = TypeDetails::getPhpType($sample) ?? TypeDetails::PHP_MIXED;
        if ($nestedType === TypeDetails::PHP_UNSUPPORTED) {
            return $this->arrayType();
        }

        if (in_array($nestedType, TypeDetails::PHP_SCALAR_TYPES, true)) {
            return $this->collectionType($nestedType . '[]');
        }

        if (is_object($sample)) {
            return $this->collectionType(get_class($sample) . '[]');
        }

        return $this->arrayType();
    }

    private function firstNonNullItem(array $array) : mixed {
        foreach ($array as $item) {
            if ($item !== null) {
                return $item;
            }
        }

        return null;
    }

    private function collectionItemType(string $typeSpec) : string {
        return str_ends_with($typeSpec, '[]') ? substr($typeSpec, 0, -2) : $typeSpec;
    }
}
