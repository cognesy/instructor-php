<?php

namespace Cognesy\Instructor\Core\StreamResponse;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Exceptions\JsonParsingException;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Chain;
use Cognesy\Instructor\Utils\Json;
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
        return Chain::make()
            ->through(fn() => $this->preventJsonSchemaResponse($preventJsonSchema, $partialResponseText))
            ->through(fn() => $this->detectNonMatchingJson($matchToExpectedFields, $partialResponseText, $responseModel))
            ->onFailure(fn($result) => throw new JsonParsingException(
                message: $result->errorMessage(),
                json: $partialResponseText,
            ))
            ->result();
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
            message: 'You started responding with JSONSchema. Respond correctly with strict JSON object data instead.',
            json: $partialResponseText,
        ));
    }

    private function isJsonSchemaResponse(string $responseText) : bool {
        try {
            $jsonFragment = Json::findPartial($responseText);
            $decoded = Json::parsePartial($jsonFragment);
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
            $decoded = Json::parsePartial($jsonFragment);
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