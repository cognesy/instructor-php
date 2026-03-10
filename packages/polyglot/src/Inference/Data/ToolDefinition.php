<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use InvalidArgumentException;

readonly final class ToolDefinition
{
    public function __construct(
        private string $name,
        private string $description,
        private array $parameters,
    ) {}

    public function name() : string {
        return $this->name;
    }

    public function description() : string {
        return $this->description;
    }

    public function parameters() : array {
        return $this->parameters;
    }

    public static function fromArray(array $tool) : self {
        $function = self::functionData($tool);

        return new self(
            name: self::requiredStringValue($function, 'name'),
            description: self::optionalStringValue($function, 'description'),
            parameters: self::optionalArrayValue($function, 'parameters'),
        );
    }

    public function toArray() : array {
        $function = ['name' => $this->name];
        if ($this->description !== '') {
            $function['description'] = $this->description;
        }
        $function['parameters'] = $this->parameters;

        return [
            'type' => 'function',
            'function' => $function,
        ];
    }

    private static function functionData(array $tool) : array {
        $function = $tool['function'] ?? null;

        return match (true) {
            is_array($function) => $function,
            isset($tool['name']) => $tool,
            default => throw new InvalidArgumentException('Tool definition must contain a function definition array.'),
        };
    }

    private static function requiredStringValue(array $data, string $key) : string {
        $value = $data[$key] ?? null;

        return match (true) {
            is_string($value) => $value,
            default => throw new InvalidArgumentException("Tool definition function field [$key] must be a string."),
        };
    }

    private static function optionalStringValue(array $data, string $key) : string {
        $value = $data[$key] ?? null;

        return match (true) {
            $value === null => '',
            is_string($value) => $value,
            default => throw new InvalidArgumentException("Tool definition function field [$key] must be a string."),
        };
    }

    private static function optionalArrayValue(array $data, string $key) : array {
        $value = $data[$key] ?? null;

        return match (true) {
            $value === null => [],
            is_array($value) => $value,
            default => throw new InvalidArgumentException("Tool definition function field [$key] must be an array."),
        };
    }
}
