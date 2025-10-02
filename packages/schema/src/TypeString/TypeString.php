<?php declare(strict_types=1);

namespace Cognesy\Schema\TypeString;

use Cognesy\Schema\Data\TypeDetails;

class TypeString implements \Stringable
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
                typeString: $typeString,
                types: ['mixed'],
            );
        }
        $types = (new TypeStringParser)->getTypes($typeString);
        $types = match(true) {
            empty($types) => ['mixed'],
            count($types) === 1 && $types[0] === '' => ['mixed'],
            count($types) === 1 && $types[0] === 'null' => ['mixed'],
            count($types) === 1 && $types[0] === 'any' => ['mixed'],
            default => $types,
        };
        return new self(
            typeString: $typeString,
            types: $types,
        );
    }

    public function firstType(): string {
        $types = $this->withoutNull($this->types);
        return match(true) {
            $this->isScalar() => $this->firstOrFail($types),
            $this->isObject() => $this->firstOrFail($types),
            $this->isEnumObject() => $this->firstOrFail($types),
            $this->isCollection() => $this->firstOrFail($types),
            $this->isArray() => TypeDetails::PHP_ARRAY,
            default => TypeDetails::PHP_MIXED,
        };
    }

    public function isNullable() : bool {
        return $this->containsNull($this->types);
    }

    public function isScalar() : bool {
        // check if every $this->types is a scalar type or null
        foreach ($this->types as $type) {
            if ($type === TypeDetails::PHP_NULL) {
                continue; // null is allowed in scalars
            }
            if ($type === TypeDetails::PHP_MIXED) {
                return false; // mixed is not a scalar type
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

    public function isUntypedObject() : bool {
        $types = $this->withoutNull($this->types);
        if (empty($types)) {
            return false;
        }
        if (count($types) === 1 && $types[0] === TypeDetails::PHP_OBJECT) {
            return true; // if only one type and it is an untyped object
        }
        return false;
    }

    public function isUntypedEnum() : bool {
        $types = $this->withoutNull($this->types);
        if (empty($types)) {
            return false;
        }
        if (count($types) === 1 && $types[0] === TypeDetails::PHP_ENUM) {
            return true; // if only one type and it is an untyped enum
        }
        return false;
    }

    public function isUnion() : bool {
        $types = $this->withoutNull($this->types);
        return count($types) > 1 || (count($types) === 1 && $types[0] !== TypeDetails::PHP_MIXED);
    }

    public function isMixed() : bool {
        if (empty($this->withoutNull($this->types))) {
            return true; // empty type string is considered mixed
        }
        // check if every $this->types is a mixed type or null
        foreach ($this->types as $type) {
            if ($type === TypeDetails::PHP_NULL) {
                continue; // null is allowed in mixed
            }
            if ($type !== TypeDetails::PHP_MIXED) {
                return false; // if any type is not mixed, it is not mixed
            }
        }
        return true;
    }

    public function hasMixed() : bool {
        $types = $this->withoutNull($this->types);
        if (empty($types)) {
            return true; // empty type string is considered mixed
        }
        foreach ($types as $type) {
            if ($type === TypeDetails::PHP_MIXED) {
                return true;
            }
        }
        return false;
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

    public function itemType() : string {
        if (!$this->isCollection()) {
            throw new \Exception('Cannot get item type from non-collection type: '.$this->typeString);
        }
        return $this->firstOrFail(array_map(
            fn($type) => $this->collectionToItemType($type),
            $this->withoutNull($this->types)
        ));
    }

    public function className() : ?string {
        if (!$this->isObject() && !$this->isEnumObject()) {
            return null; // only objects and enums have class names
        }
        $types = $this->withoutNull($this->types);
        foreach ($types as $type) {
            if ($type === TypeDetails::PHP_OBJECT || $type === TypeDetails::PHP_ENUM) {
                continue; // skip untyped object and enum
            }
            if (class_exists($type)) {
                return $type; // return the class name if it exists
            }
        }
        return null; // no class name found
    }

    /**
     * @return list<string>
     */
    public function types(): array {
        return $this->types;
    }

    public function toString() : string {
        return implode('|', $this->types);
    }

    #[\Override]
    public function __toString() : string {
        return $this->toString();
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

    private function containsNull(array $types) : bool {
        foreach ($types as $type) {
            if ($type === TypeDetails::PHP_NULL) {
                return true;
            }
        }
        return false;
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

    private function containsManyTypes(array $types) : bool {
        return count($this->withoutNull($types)) > 1;
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
            if ($this->endsWithBrackets($type)) {
                continue; // skip collection types
            }
            if (in_array($type, TypeDetails::PHP_SCALAR_TYPES)) {
                continue; // skip scalar types
            }
            if ($type === TypeDetails::PHP_ARRAY) {
                continue; // skip array type
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