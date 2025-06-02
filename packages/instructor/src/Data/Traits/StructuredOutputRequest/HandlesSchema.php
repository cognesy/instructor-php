<?php

namespace Cognesy\Instructor\Data\Traits\StructuredOutputRequest;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesSchema
{
    public function responseModel() : ?ResponseModel {
        return $this->responseModel;
    }

    public function requestedSchema() : string|array|object {
        return $this->requestedSchema;
    }

    public function toolName() : string {
        return $this->responseModel
            ? $this->responseModel->toolName()
            : $this->config->toolName();
    }

    public function toolDescription() : string {
        return $this->responseModel
            ? $this->responseModel->toolDescription()
            : $this->config->toolDescription();
    }

    public function responseFormat() : array {
        return match($this->mode()) {
            OutputMode::Json => [
                'type' => 'json_object',
                'schema' => $this->jsonSchema(),
            ],
            OutputMode::JsonSchema => [
                'type' => 'json_schema',
                'description' => $this->toolDescription(),
                'json_schema' => [
                    'name' => $this->schemaName(),
                    'schema' => $this->jsonSchema(),
                    'strict' => true,
                ],
            ],
            default => []
        };
    }

    public function jsonSchema() : ?array {
        return $this->responseModel?->toJsonSchema();
    }

    public function toolCallSchema() : ?array {
        return match($this->mode()) {
            OutputMode::Tools => $this->responseModel?->toolCallSchema(),
            default => [],
        };
    }

    public function toolChoice() : string|array {
        return match($this->mode()) {
            OutputMode::Tools => [
                'type' => 'function',
                'function' => [
                    'name' => $this->toolName()
                ]
            ],
            default => [],
        };
    }

    public function schemaName() : string {
        return $this->responseModel?->schemaName() ?? $this->config->schemaName() ?? 'default_schema';
    }
}