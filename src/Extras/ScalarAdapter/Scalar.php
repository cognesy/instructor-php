<?php

namespace Cognesy\Instructor\Extras\ScalarAdapter;

use Cognesy\Instructor\Contracts\CanDeserializeJson;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformResponse;

class Scalar implements CanProvideSchema, CanDeserializeJson, CanTransformResponse
{
    public mixed $value;

    public string $name = 'value';
    public string $description = 'Response value';
    public ValueType $type = ValueType::STRING;
    public array $options = [];
    public bool $required = true;
    public mixed $defaultValue = null;

    public function __construct(
        string $name = 'value',
        string $description = 'Response value',
        ValueType $type = ValueType::STRING,
        bool $required = true,
        mixed $defaultValue = null,
        array $options = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->required = $required;
        $this->defaultValue = $defaultValue;
        $this->options = $options;
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
                    'description' => $this->description,
                    'type' => $this->type->toJsonType(),
                ],
            ],
        ];
        if ($this->required) {
            $array['required'] = [$this->name];
        }
        if (!empty($this->options)) {
            $array['properties'][$this->name]['enum'] = $this->options;
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

    public function transform() : mixed {
        return $this->value;
    }

    static public function integer(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
        array $options = []
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::INTEGER,
            required: $required,
            defaultValue: $defaultValue,
            options: $options,
        );
    }

    static public function float(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
        array $options = []
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::FLOAT,
            required: $required,
            defaultValue: $defaultValue,
            options: $options,
        );
    }

    static public function string(
        string $name = 'value',
        string $description = 'Response value',
        bool $required = true,
        mixed $defaultValue = null,
        array $options = []
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::STRING,
            required: $required,
            defaultValue: $defaultValue,
            options: $options,
        );
    }

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

    static public function select(
        array $options,
        string $name = 'option',
        string $description = 'Select option',
        bool $required = true,
        mixed $defaultValue = null,
    ) : self {
        return new self(
            name: $name,
            description: $description,
            type: ValueType::STRING,
            required: $required,
            defaultValue: $defaultValue,
            options: $options,
        );
    }
}
