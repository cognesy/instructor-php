<?php

namespace Cognesy\Instructor\LLMs;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Exceptions\JSONParsingException;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\JsonParser;
use Exception;

trait ValidatesPartialResponse
{
    public function validatePartialResponse(
        string $partialResponseText,
        ResponseModel $responseModel,
        bool $preventJsonSchema,
        bool $matchToExpectedFields
    ) : void {
        if ($preventJsonSchema) {
            $this->preventJsonSchemaResponse($partialResponseText);
        }
        if ($matchToExpectedFields) {
            $this->detectNonMatchingJson($partialResponseText, $responseModel);
        }
    }

    /// VALIDATIONS //////////////////////////////////////////////////////////////////

    private function preventJsonSchemaResponse(string $partialResponseText) {
        if (!$this->isJsonSchemaResponse($partialResponseText)) {
            return;
        }
        throw new JsonParsingException(
            message: 'You started responding with JSONSchema. Respond with JSON data instead.',
            json: $partialResponseText,
        );
    }

    private function isJsonSchemaResponse(string $responseText) : bool {
        // ...detect JSONSchema response
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

    private function detectNonMatchingJson(string $responseText, ResponseModel $responseModel) : void {
        if ($this->isMatchingResponseModel($responseText, $responseModel)) {
            return;
        }
        throw new JsonParsingException(
            message: 'JSON does not match schema.',
            json: $responseText,
        );
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