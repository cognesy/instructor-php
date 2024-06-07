<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;

trait HandlesSchema
{
    protected ResponseModelFactory $responseModelFactory;

    public function createResponseModel(string|array|object $responseModel) : ResponseModel {
        return $this->responseModelFactory->fromAny($responseModel);
    }

    public function createJsonSchema(string|array|object $responseModel) : array {
        return $this->responseModelFactory->fromAny($responseModel)->toJsonSchema();
    }

    public function createJsonSchemaString(string|array|object $responseModel) : string {
        return json_encode($this->createJsonSchema($responseModel));
    }
}