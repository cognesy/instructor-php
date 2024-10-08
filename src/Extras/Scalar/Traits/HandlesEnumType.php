<?php
namespace Cognesy\Instructor\Extras\Scalar\Traits;

use BackedEnum;
use Cognesy\Instructor\Extras\Scalar\ValueType;
use Exception;
use ReflectionEnum;

trait HandlesEnumType
{
    public array $options = [];
    /** @var class-string $enumType */
    public ?string $enumType = null;

    private function initEnum(?string $enumType, ValueType $type) : void {
        if (empty($enumType)) {
            return;
        }
        $this->enumType = $enumType;
        $this->options = $this->getEnumValues($enumType);
        if (!empty($this->options)) {
            $this->type = $this->getEnumValueType();
        }
    }

    private function getEnumValueType() : ValueType {
        if (empty($this->options)) {
            throw new \Exception("Enum options are not set");
        }
        $first = $this->options[0];
        if (is_string($first)) {
            return ValueType::STRING;
        }
        if (is_int($first)) {
            return ValueType::INTEGER;
        }
        throw new Exception("Enum type is not supported: " . gettype($first));
    }

    private function getEnumValues(?string $enumType) : mixed
    {
        if (empty($enumType)) {
            return [];
        }
        if (!enum_exists($enumType)) {
            throw new \Exception("Enum class does not exist: {$enumType}");
        }
        $enumReflection = new ReflectionEnum($enumType);
        $cases = $enumReflection->getCases();
        $values = [];
        foreach ($cases as $case) {
            $values[] = $case->getValue()->value;
        }
        return $values;
    }

    static private function isEnum(?string $enumType) : bool {
        if (empty($enumType)) {
            return false;
        }
        return !empty($enumType)
            && class_exists($enumType)
            && is_subclass_of($enumType, BackedEnum::class);
    }
}
