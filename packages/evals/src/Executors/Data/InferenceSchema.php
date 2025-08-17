<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors\Data;

use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class InferenceSchema implements CanProvideJsonSchema
{
    public function __construct(
        private string $toolName,
        private string $toolDescription,
        private array $schema = [],
    ) {}

    public function schema() : array {
        return $this->schema;
    }

    public function responseFormatJson() : array {
        return [
            'type' => 'json_object',
            'schema' => $this->schema,
        ];
    }

    public function responseFormatJsonSchema() : array {
        return [
            'type' => 'json_schema',
            'description' => $this->toolDescription,
            'json_schema' => [
                'name' => $this->toolName,
                'schema' => $this->schema,
                'strict' => true,
            ],
        ];
    }

    public function tools() : array {
        return [[
            'type' => 'function',
            'function' => [
                'name' => $this->toolName,
                'description' => $this->toolDescription,
                'parameters' => $this->schema,
            ],
        ]];
    }

    public function toolChoice() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->toolName,
            ]
        ];
    }

    public function toJsonSchema(): array {
        return $this->schema;
    }
}
