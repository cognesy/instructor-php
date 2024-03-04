<?php

namespace Cognesy\Instructor\Extras\Scalars;

use Cognesy\Instructor\Contracts\CanDeserializeJson;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformResponse;
use ReflectionEnum;

/**
 * Scalar value adapter.
 * Improved DX via simplified retrieval of scalar value from LLM response.
 */
class Scalar implements CanProvideSchema, CanDeserializeJson, CanTransformResponse
{
    public mixed $value;

    public string $name = 'value';
    public string $description = 'Response value';
    public ValueType $type = ValueType::STRING;
    public array $options = [];
    public bool $required = true;
    public mixed $defaultValue = null;
    public ?string $enumType = null;

    public function __construct(
        string $name = 'value',
        string $description = 'Response value',
        ValueType $type = ValueType::STRING,
        bool $required = true,
        mixed $defaultValue = null,
        string $enumType = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->required = $required;
        $this->defaultValue = $defaultValue;
        $this->enumType = $enumType;
        $this->options = $this->getEnumValues($enumType);
        if (empty($this->options)) {
            $this->type = $type;
        } else {
            $this->type = $this->getEnumValueType();
        }
    }

    /**
     * Custom JSON schema for scalar value - we ignore all fields in this class and pass only what we want
     * by manually creating the array representing JSON Schema of our desired structure.
     */
    public function toJsonSchema() : array {
        $array = [
            '$comment' => Scalar::class,
            'type' => 'object',
            'properties' => [
                $this->name => [
                    '$comment' => $this->enumType,
                    'description' => $this->description,
                    'type' => $this->type->toJsonType(),
                ],
            ],
        ];
        if (!empty($this->options)) {
            $array['properties'][$this->name]['enum'] = $this->options;
        }
        if ($this->required) {
            $array['required'] = [$this->name];
        }
        return $array;
    }

    /**
     * Deserialize JSON into scalar value
     */
    public function fromJson(string $json) : self {
        $array = json_decode($json, true);
        $value = $array[$this->name] ?? $this->defaultValue;
        if (($value === null) && $this->required) {
            throw new \Exception("Value is required");
        }
        try {
            $this->value = match ($this->type) {
                ValueType::STRING => (string) $value,
                ValueType::INTEGER => (int) $value,
                ValueType::FLOAT => (float) $value,
                ValueType::BOOLEAN => (bool) $value,
            };
        } catch (\Throwable $e) {
            throw new \Exception("Failed to deserialize value: " . $e->getMessage());
        }
        if (!empty($this->options) && !in_array($this->value, $this->options)) {
            throw new \Exception("Value is not in the list of allowed options");
        }
        return $this;
    }

    /**
     * Transform response model into scalar value
     */
    public function transform() : mixed {
        return $this->value;
    }

    /**
     * Create a new Scalar adapter for integer
     */
    static public function integer(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::INTEGER,
            required: $required,
            defaultValue: $defaultValue,
        );
    }

    /**
     * Create a new Scalar adapter for float
     */
    static public function float(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::FLOAT,
            required: $required,
            defaultValue: $defaultValue,
        );
    }

    /**
     * Create a new Scalar adapter for string
     */
    static public function string(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::STRING,
            required: $required,
            defaultValue: $defaultValue,
        );
    }

    /**
     * Create a new Scalar adapter for boolean
     */
    static public function boolean(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::BOOLEAN,
            required: $required,
            defaultValue: $defaultValue,
        );
    }

    /**
     * Create a new enum-like Scalar adapter (single choice from list of string options)
     */
    static public function enum(
        string $enumType,
        string $name = 'enum',
        string $description = 'Select correct option',
        bool   $required = true,
        mixed  $defaultValue = null,
    ) : self {
        if (!class_exists($enumType) || !is_subclass_of($enumType, \BackedEnum::class)) {
            throw new \Exception("Enum class does not exist or is not BackedEnum: {$enumType}");
        }
        return new self(
            name: $name,
            description: $description,
            required: $required,
            defaultValue: $defaultValue,
            enumType: $enumType,
        );
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
        throw new \Exception("Enum type is not supported: " . gettype($first));
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
}
