<?php

namespace Cognesy\Instructor\Data\Traits;

use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;

trait HandlesSchema
{
    private ResponseModelFactory $responseModelFactory;

    private string $defaultToolName = 'extract_data';
    private string $defaultToolDescription = 'Extract data from provided content';
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

    public function withResponseModel(ResponseModel $responseModel) : self {
        $this->responseModel = $responseModel;
        return $this;
    }

    public function toolName() : string {
        return $this->responseModel ? $this->responseModel->toolName() : $this->toolName;
    }

    public function toolDescription() : string {
        return $this->responseModel ? $this->responseModel->toolDescription() : $this->toolDescription;
    }

    public function jsonSchema() : array {
        return $this->responseModel->toJsonSchema();
    }

    public function toolCallSchema() : array {
        return $this->responseModel->toolCallSchema();
    }

    public function toolChoice() : string|array {
        return [
            'type' => 'function',
            'function' => ['name' => $this->toolName()]
        ];
    }

    public function responseFormat() : array {
        return [
            'type' => 'json_object',
            'schema' => $this->jsonSchema()
        ];
    }
}