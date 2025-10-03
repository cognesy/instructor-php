<?php declare(strict_types=1);

namespace Cognesy\Schema\Reflection;

use ReflectionEnum;

class EnumInfo extends ClassInfo
{
    protected ReflectionEnum $reflectionEnum;

    /**
     * @param class-string $class
     */
    public function __construct(string $class) {
        parent::__construct($class);
        /** @var class-string<\UnitEnum> $enumClass */
        $enumClass = $this->class;
        $this->reflectionEnum = new ReflectionEnum($enumClass);
    }

    #[\Override]
    public function isEnum() : bool {
        return true;
    }

    #[\Override]
    public function isBacked() : bool {
        return isset($this->reflectionEnum)
            && $this->reflectionEnum->isBacked();
    }

    public function enumBackingType() : string {
        if (!isset($this->reflectionEnum)) {
            throw new \Exception("Not an enum");
        }
        $backingType = $this->reflectionEnum->getBackingType();
        return $backingType instanceof \ReflectionNamedType ? $backingType->getName() : '';
    }

    /** @return string[]|int[] */
    public function enumValues() : array {
        $enum = $this->reflectionEnum ?? throw new \Exception("Not an enum");

        $values = [];
        foreach ($enum->getCases() as $item) {
            $enumInstance = $item->getValue();
            if ($enumInstance instanceof \BackedEnum) {
                $values[] = $enumInstance->value;
            }
        }
        return $values;
    }
}
