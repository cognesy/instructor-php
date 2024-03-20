<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanHandlePartialResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Exceptions\JSONParsingException;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Result;
use Exception;

class PartialResponseHandler implements CanHandlePartialResponse
{
    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchemaResponse = true;

    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
    ) {}

    public function toPartialResponse(string $jsonData, ResponseModel $responseModel): Result
    {
        // ...detect JSONSchema response
        if ($this->preventJsonSchemaResponse && $this->isJsonSchemaResponse($jsonData)) {
            throw new JsonParsingException(
                message: 'You responded with JSONSchema. Respond with JSON instead.',
                json: $jsonData,
            );
        }

        // ...detect non-matching response JSON
        if ($this->matchToExpectedFields && !$this->isMatchingResponseModel($jsonData, $responseModel)) {
            throw new JsonParsingException(
                message: 'JSON does not match schema.',
                json: $jsonData,
            );
        }

        // ...deserialize
        $result = $this->responseDeserializer->deserialize($jsonData, $responseModel);
        if ($result->isFailure()) {
            return $result;
        }
        $object = $result->value();

        // ...transform
        $result = $this->responseTransformer->transform($object);
        if ($result->isFailure()) {
            return $result;
        }

        return $result;
    }

    private function isMatchingResponseModel(string $jsonData, ResponseModel $responseModel): bool
    {
        // ...check for response model property names
        $propertyNames = isset($responseModel->schema->properties)
            ? array_keys($responseModel->schema->properties)
            : [];
        if (empty($propertyNames)) {
            return true;
        }
        // ...detect matching response model
        try {
            $decoded = json_decode($jsonData, true);
        } catch (Exception $e) {
            return false;
        }
        // Question: how to make this work while we're getting partially retrieved field names
        $decodedKeys = array_filter(array_keys($decoded));
        if (empty($decodedKeys)) {
            return true;
        }
        return Arrays::isSubset($decodedKeys, $propertyNames);
    }

    private function isJsonSchemaResponse(string $jsonData): bool
    {
        // ...detect JSONSchema response
        try {
            $decoded = json_decode($jsonData, true);
        } catch (Exception $e) {
            return false;
        }
        if (isset($decoded['type']) && $decoded['type'] === 'object') {
            return true;
        }
        return false;
    }
}