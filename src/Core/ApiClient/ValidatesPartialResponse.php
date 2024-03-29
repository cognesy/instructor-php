<?php

namespace Cognesy\Instructor\Core\ApiClient;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Exceptions\JsonParsingException;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\JsonParser;
use Cognesy\Instructor\Utils\Result;
use Exception;

trait ValidatesPartialResponse
{
    public function validatePartialResponse(
        string $partialResponseText,
        ResponseModel $responseModel,
        bool $preventJsonSchema,
        bool $matchToExpectedFields
    ) : Result {
        $result = $this->preventJsonSchemaResponse($preventJsonSchema, $partialResponseText);
        if ($result->isFailure()) {
            return $result;
        }
        $result = $this->detectNonMatchingJson($matchToExpectedFields, $partialResponseText, $responseModel);
        if ($result->isFailure()) {
            return $result;
        }
        return Result::success(true);
    }

    /// VALIDATIONS //////////////////////////////////////////////////////////////////

    private function preventJsonSchemaResponse(bool $check, string $partialResponseText) : Result {
        if (!$check) {
            return Result::success(true);
        }
        if (!$this->isJsonSchemaResponse($partialResponseText)) {
            return Result::success(true);
        }
        return Result::failure(new JsonParsingException(
            message: 'You started responding with JSONSchema. Respond with JSON data instead.',
            json: $partialResponseText,
        ));
    }

    private function isJsonSchemaResponse(string $responseText) : bool {
        try {
            $jsonFragment = Json::findPartial($responseText);
            $decoded = (new JsonParser)->parse($jsonFragment, true);
        } catch (Exception $e) {
            // also covers no JSON at all - which is fine, as some models will respond with text
            return false;
        }
        if (isset($decoded['type']) && $decoded['type'] === 'object') {
            return true;
        }
        return false;
    }

    private function detectNonMatchingJson(bool $check, string $responseText, ResponseModel $responseModel) : Result {
        if (!$check) {
            return Result::success(true);
        }
        if ($this->isMatchingResponseModel($responseText, $responseModel)) {
            return Result::success(true);
        }
        return Result::failure(new JsonParsingException(
            message: 'JSON does not match schema.',
            json: $responseText,
        ));
    }

    private function isMatchingResponseModel(
        string        $partialResponseText,
        ResponseModel $responseModel
    ) : bool {
        // ...check for response model property names
        $propertyNames = $responseModel->schema->getPropertyNames();
        if (empty($propertyNames)) {
            return true;
        }
        // ...detect matching response model
        try {
            $jsonFragment = Json::findPartial($partialResponseText);
            $decoded = (new JsonParser)->parse($jsonFragment, true);
            // we can try removing last item as it is likely to be still incomplete
            $decoded = Arrays::removeTail($decoded, 1);
        } catch (Exception $e) {
            return false;
        }
        // Question: how to make this work while we're getting partially
        // retrieved field names
        $decodedKeys = array_filter(array_keys($decoded));
        if (empty($decodedKeys)) {
            return true;
        }
        return Arrays::isSubset($decodedKeys, $propertyNames);
    }
}