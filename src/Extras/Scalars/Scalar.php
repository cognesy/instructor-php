<?php

namespace Cognesy\Instructor\Extras\Scalars;

use BackedEnum;
use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Deserialization\Exceptions\DeserializationException;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Exception;
use ReflectionEnum;

/**
 * Scalar value adapter.
 * Improved DX via simplified retrieval of scalar value from LLM response.
 */
class Scalar implements CanProvideJsonSchema, CanDeserializeSelf, CanTransformSelf, CanValidateSelf
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
                    '$comment' => $this->enumType ?? '',
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
    public function fromJson(string $jsonData) : static {
        if (empty($jsonData)) {
            $this->value = $this->defaultValue;
            return $this;
        }
        try {
            // decode JSON into array
            $array = Json::parse($jsonData);
        } catch (Exception $e) {
            throw new DeserializationException($e->getMessage(), $this->name, $jsonData);
        }
        // check if value exists in JSON
        $this->value = $array[$this->name] ?? $this->defaultValue;
        return $this;
    }

    /**
     * Validate scalar value
     */
    public function validate() : ValidationResult {
        $errors = [];
        if ($this->required && $this->value === null) {
            $errors[] = new ValidationError(
                $this->name,
                $this->value,
                "Value '{$this->name}' is required");
        }
        if (!empty($this->options) && !in_array($this->value, $this->options)) {
            $errors[] = new ValidationError(
                $this->name,
                $this->value,
                "Value '{$this->name}' must be one of: " . implode(", ", $this->options));
        }
        return ValidationResult::make($errors, "Validation failed for '{$this->name}'");
    }

    /**
     * Transform response model into scalar value
     */
    public function transform() : mixed {
        if (self::isEnum($this->enumType)) {
            return ($this->enumType)::from($this->value);
        }
        // try to match it to supported type
        return match ($this->type) {
            ValueType::STRING => (string) $this->value,
            ValueType::INTEGER => (int) $this->value,
            ValueType::FLOAT => (float) $this->value,
            ValueType::BOOLEAN => (bool) $this->value,
        };
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
        if (!self::isEnum($enumType)) {
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

    ////////////////////////////////////////////////////////////////////////////////////////////

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

    static private function isEnum(?string $enumType) : bool {
        if (empty($enumType)) {
            return false;
        }
        return !empty($enumType)
            && class_exists($enumType)
            && is_subclass_of($enumType, BackedEnum::class);
    }
}
