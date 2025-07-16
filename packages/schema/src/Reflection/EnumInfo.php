<?php declare(strict_types=1);

namespace Cognesy\Schema\Reflection;

use ReflectionEnum;

class EnumInfo extends ClassInfo
{
    protected ReflectionEnum $reflectionEnum;

    public function __construct(string $class) {
        parent::__construct($class);
        $this->reflectionEnum = new ReflectionEnum($class);
    }

    public function isEnum() : bool {
        return true;
    }

    public function isBacked() : bool {
        return isset($this->reflectionEnum)
            && $this->reflectionEnum->isBacked();
    }

    public function enumBackingType() : string {
        return isset($this->reflectionEnum)
            ? $this->reflectionEnum->getBackingType()?->getName()
            : throw new \Exception("Not an enum");
    }

    /** @return string[]|int[] */
    public function enumValues() : array {
        $enum = $this->reflectionEnum ?? throw new \Exception("Not an enum");

        $values = [];
        foreach ($enum->getCases() as $item) {
            $values[] = $item->getValue()->value;
        }
        return $values;
    }
}
