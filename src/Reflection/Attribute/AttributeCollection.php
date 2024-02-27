<?php

namespace Cognesy\Instructor\Reflection\Attribute;

use ReflectionAttribute;

class AttributeCollection
{
    private array $attributes;

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @return ReflectionAttribute[]
     */
    public function all(): array
    {
        return $this->attributes;
    }

    public function has(string $name): bool
    {
        foreach($this->attributes as $attribute) {
            if ($attribute->getName() === $name) {
                return true;
            }
        }
        return false;
    }

    public function get(string $name): ?ReflectionAttribute
    {
        foreach($this->attributes as $attribute) {
            if ($attribute->getName() === $name) {
                return $attribute;
            }
        }
        return null;
    }

    /**
     * @return ReflectionAttribute[]
     */
    public function getEach(string $name): array
    {
        $list = [];
        foreach($this->attributes as $attribute) {
            if ($attribute->getName() === $name) {
                $list[] = $attribute;
            }
        }
        return $list;
    }
}