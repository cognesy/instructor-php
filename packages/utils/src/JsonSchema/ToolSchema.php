<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema;

final readonly class ToolSchema
{
    public function __construct(
        public string $name,
        public string $description,
        public JsonSchema $parameters,
    ) {}

    public static function make(
        string $name,
        string $description,
        JsonSchema $parameters,
    ) : self {
        return new self($name, $description, $parameters);
    }

    public function toArray() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters->toJsonSchema(),
            ],
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            name: $data['name'] ?? 'unnamed',
            description: $data['description'] ?? '',
            parameters: JsonSchema::fromArray($data['parameters'] ?? []),
        );
    }
}