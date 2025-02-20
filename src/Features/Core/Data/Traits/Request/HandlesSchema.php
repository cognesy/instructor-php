<?php

namespace Cognesy\Instructor\Features\Core\Data\Traits\Request;

use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\LLM\LLM\Enums\Mode;
use Cognesy\Utils\Str;

trait HandlesSchema
{
    private string $toolName;
    private string $toolDescription;
    private string|array|object $requestedSchema;
    private ?ResponseModel $responseModel = null;

    public function responseModel() : ?ResponseModel {
        return $this->responseModel;
    }

    public function requestedSchema() : string|array|object {
        return $this->requestedSchema;
    }

    public function toolName() : string {
        return $this->responseModel
            ? $this->responseModel->toolName()
            : $this->toolName;
    }

    public function toolDescription() : string {
        return $this->responseModel
            ? $this->responseModel->toolDescription()
            : $this->toolDescription;
    }

    public function responseFormat() : array {
        return match($this->mode()) {
            Mode::Json => [
                'type' => 'json_object',
                'schema' => $this->jsonSchema(),
            ],
            Mode::JsonSchema => [
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
        return $this->responseModel?->toolCallSchema();
    }

    public function toolChoice() : string|array {
        return [
            'type' => 'function',
            'function' => ['name' => $this->toolName()]
        ];
    }

    public function schemaName() : string {
        $requestedSchema = $this->requestedSchema();
        $name = match(true) {
            is_string($requestedSchema) => $requestedSchema,
            is_array($requestedSchema) => $requestedSchema['name'] ?? 'default_schema',
            is_object($requestedSchema) => get_class($requestedSchema),
            default => 'default_schema',
        };
        if (Str::startsWith($name, '\\')) {
            $name = substr($name, 1);
        }
        $name = str_replace('\\', '_', $name);
        return $name;
    }
}