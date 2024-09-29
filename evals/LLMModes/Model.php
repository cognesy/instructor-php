<?php

namespace Cognesy\Evals\LLMModes;

class Model
{
    public function __construct(
        private array $schema = [],
    ) {}

    public function schema() : array {
        return $this->schema ?: [
            'type' => 'object',
            'description' => 'Company data',
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
                'name' => 'store_company',
                'description' => 'Save company data',
                'parameters' => $this->schema(),
            ],
        ]];
    }

    public function responseFormatJsonSchema() : array {
        return [
            'type' => 'json_schema',
            'description' => 'Company data',
            'json_schema' => [
                'name' => 'store_company',
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
                'name' => 'store_company'
            ]
        ];
    }
}