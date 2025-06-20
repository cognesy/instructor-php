<?php

namespace Cognesy\Schema\TypeString;

use Cognesy\Schema\Data\TypeDetails;

class TypeString
{
    private string $typeString;
    /** @var list<string> A list of normalized, unique type names. */
    private readonly array $types;

    private function __construct(
        string $typeString = '',
        array $types = [],
    ) {
        $this->types = $this->normalizeTypes($types);
        $this->typeString = $typeString;
    }

    public static function fromString(string $typeString) : self {
        if (empty($typeString)) {
            return new self(
                typeString: '',
                types: [],
            );
        }
        $types = (new TypeStringParser)->getTypes($typeString);
        $trimmedTypes = array_map('trim', $types);
        $uniqueTypes = array_unique($trimmedTypes);
        $typeString = implode('|', $uniqueTypes);
        return new self(
            typeString: $typeString,
            types: $types,
        );
    }

    public function firstType(): string {
        return match(true) {
            $this->isScalar() => $this->firstOrFail($this->types),
            $this->isObject() => $this->firstOrFail($this->types),
            $this->isEnumObject() => $this->firstOrFail($this->types),
            $this->isCollection() => $this->firstOrFail($this->types),
            $this->isArray() => TypeDetails::PHP_ARRAY,
            default => TypeDetails::PHP_MIXED,
        };
    }

    /**
     * @return list<string>
     */
    public function types(): array {
        return $this->types;
    }

    public function isEmpty() : bool {
        return empty($this->types);
    }

    public function isScalar() : bool {
        // check if every $this->types is a scalar type or null
        foreach ($this->types as $type) {
            if ($type === TypeDetails::PHP_NULL) {
                continue; // null is allowed in scalars
            }
            if (!in_array($type, TypeDetails::PHP_SCALAR_TYPES)) {
                return false;
            }
        }
        return true;
    }

    public function isArray() : bool {
        if (empty($this->withoutNull($this->types))) {
            return false;
        }
        // check if every $this->types is an array type or null
        foreach ($this->types as $type) {
            if ($type === TypeDetails::PHP_NULL) {
                continue; // null is allowed in arrays
            }
            if ($type !== TypeDetails::PHP_ARRAY) {
                return false;
            }
        }
        return true;
    }

    public function isObject() : bool {
        if (empty($this->withoutNull($this->types))) {
            return false;
        }
        // check if every $this->types is an object type or null
        foreach ($this->types as $type) {
            if ($type === TypeDetails::PHP_NULL) {
                continue; // null is allowed in objects
            }
            if ($type === TypeDetails::PHP_ARRAY) {
                return false; // array type is not an object
            }
            if (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) {
                return false; // scalar types are not objects
            }
            if ($this->endsWithBrackets($type)) {
                return false; // collection types are not objects
            }
            if (class_exists($type)) {
                return true; // if class exists, it is an object type
            }
        }
        return true;
    }

    public function isEnumObject() : bool {
        if (!$this->isObject()) {
            return false; // if not an object, cannot be an enum
        }
        // check if every $this->types is an enum type or null
        foreach ($this->types as $type) {
            if ($type === TypeDetails::PHP_NULL) {
                continue; // null is allowed in enums
            }
            if (!class_exists($type)) {
                return false; // if no enum class found, it is not an enum
            }
            if (!is_subclass_of($type, \BackedEnum::class)) {
                return false; // if not a backed enum, it is not an enum
            }
        }
        return true; // all types are enums
    }

    public function isCollection() : bool {
        if (empty($this->withoutNull($this->types))) {
            return false;
        }
        foreach ($this->types as $type) {
            if ($type === TypeDetails::PHP_NULL) {
                continue; // null is allowed in collections
            }
            if (!$this->endsWithBrackets($type)) {
                return false;
            }
        }
        return true;
    }

    public function getItemType() : string {
        if (!$this->isCollection()) {
            throw new \Exception('Cannot get item type from non-collection type: '.$this->typeString);
        }
        return $this->firstOrFail(array_map(
            fn($type) => $this->collectionToItemType($type),
            $this->withoutNull($this->types)
        ));
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    private function normalizeTypes(array $types) : array {
        $normalized = $types;
        if ($this->containsUntypedObject($types) && $this->containsTypedObject($types)) {
            $normalized = $this->withoutUntypedObject($normalized);
        }
        if ($this->containsUntypedArray($types) && $this->containsCollection($types)) {
            $normalized = $this->withoutUntypedArray($normalized);
        }
        $normalized = $this->withoutEmpty($normalized);
        return $normalized;
    }

    private function firstOrFail(array $types) : string {
        $withoutNull = $this->withoutNull($this->types);
        $count = count($withoutNull);
        return match (true) {
            $count === 0 => TypeDetails::PHP_MIXED,
            $count === 1 => reset($types),
            default => reset($types),
        };
    }

    private function containsUntypedObject(array $types) : bool {
        foreach ($types as $type) {
            if ($type === TypeDetails::PHP_OBJECT) {
                return true;
            }
        }
        return false;
    }

    private function containsUntypedArray(array $types) : bool {
        foreach ($types as $type) {
            if ($type === TypeDetails::PHP_ARRAY) {
                return true;
            }
        }
        return false;
    }

    private function containsCollection(array $types) : bool {
        foreach ($types as $type) {
            if ($this->endsWithBrackets($type)) {
                return true;
            }
        }
        return false;
    }

    private function containsScalar(array $types) : bool {
        foreach ($types as $type) {
            if (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) {
                return true;
            }
        }
        return false;
    }

    private function containsMixed(array $types) : bool {
        foreach ($types as $type) {
            if ($type === TypeDetails::PHP_MIXED) {
                return true;
            }
        }
        return false;
    }

    private function collectionToItemType(string $collectionType) : string {
        return substr($collectionType, 0, -2);
    }

    private function endsWithBrackets(string $type) : bool {
        return substr($type, -2) === '[]';
    }

    private function withoutNull($types) : array {
        return array_filter($types, fn($type) => $type !== TypeDetails::PHP_NULL);
    }

    private function withoutUntypedObject(array $types) : array {
        return array_filter($types, fn($type) => $type !== TypeDetails::PHP_OBJECT);
    }

    private function withoutUntypedArray(array $types) : array {
        return array_filter($types, fn($type) => $type !== TypeDetails::PHP_ARRAY);
    }

    private function withoutEmpty(array $normalized) : array {
        return array_filter($normalized, fn($type) => !empty($type));
    }

    private function containsTypedObject(array $types) : bool {
        foreach ($types as $type) {
            if (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) {
                continue; // skip scalar types
            }
            if ($type === TypeDetails::PHP_ARRAY) {
                continue; // skip array type
            }
            if ($this->endsWithBrackets($type)) {
                continue; // skip collection types
            }
            if ($type === TypeDetails::PHP_OBJECT) {
                continue; // skip untyped object
            }
            if (class_exists($type)) {
                return true; // if class exists, it is a typed object
            }
        }
        return false;
    }
}