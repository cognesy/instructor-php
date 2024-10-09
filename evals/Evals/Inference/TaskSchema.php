<?php

namespace Cognesy\Evals\Evals\Inference;

class TaskSchema
{
    public function __construct(
        private string $toolName = 'store_company',
        private string $schemaDescription = 'Company data',
        private array  $schema = [],
    ) {}

    public function schema() : array {
        return $this->schema ?: [
            'type' => 'object',
            'description' => $this->schemaDescription,
            'properties' => [
                'year' => [
                    'type' => 'integer',
                    'description' => 'Founding year',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Company name',
                ],
            ],
            'required' => ['name', 'year'],
            'additionalProperties' => false,
        ];
    }

    public function tools() : array {
        return [[
            'type' => 'function',
            'function' => [
                'name' => $this->toolName,
                'description' => $this->schemaDescription,
                'parameters' => $this->schema(),
            ],
        ]];
    }

    public function responseFormatJsonSchema() : array {
        return [
            'type' => 'json_schema',
            'description' => $this->schemaDescription,
            'json_schema' => [
                'name' => $this->schemaName,
                'schema' => $this->schema(),
                'strict' => true,
            ],
        ];
    }

    public function responseFormatJson() : array {
        return [
            'type' => 'json_object',
            'schema' => $this->schema(),
        ];
    }

    public function toolChoice() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->toolName,
            ]
        ];
    }
}
