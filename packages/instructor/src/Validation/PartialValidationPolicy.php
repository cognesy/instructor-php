<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateContracts\CanCarryState;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Json\JsonParsingException;
use Cognesy\Utils\Result\Result;
use Exception;

/**
 * Validates partial response text during streaming according to early policies:
 * - prevent JSON Schema-shaped responses when strict JSON object data is expected
 * - ensure keys match expected ResponseModel (subset check, tolerant for partials)
 */
class PartialValidationPolicy
{
    public function validatePartialResponse(
        string $partialResponseText,
        ResponseModel $responseModel,
        bool $preventJsonSchema,
        bool $matchToExpectedFields
    ) : Result {
        return $this->makePartialValidationPipeline(
                $partialResponseText,
                $responseModel,
                $preventJsonSchema,
                $matchToExpectedFields
            )
            ->executeWith(ProcessingState::with($partialResponseText))
            ->result();
    }

    public function makePartialValidationPipeline(
        string $partialResponseText,
        ResponseModel $responseModel,
        bool $preventJsonSchema,
        bool $matchToExpectedFields
    ) : Pipeline {
        return Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn(string $text) => $this->preventJsonSchemaResponse($preventJsonSchema, $text))
            ->through(fn(string $text) => $this->detectNonMatchingJson($matchToExpectedFields, $text, $responseModel))
            ->onFailure(fn(CanCarryState $state) => throw new JsonParsingException(
                message: (string) $state->result()->error(),
                json: $partialResponseText,
            ))
            ->create();
    }

    private function preventJsonSchemaResponse(bool $check, string $partialResponseText) : Result {
        if (!$check) {
            return Result::success($partialResponseText);
        }
        if (!$this->isJsonSchemaResponse($partialResponseText)) {
            return Result::success($partialResponseText);
        }
        return Result::failure(new JsonParsingException(
            message: 'You started responding with JSONSchema. Respond correctly with strict JSON object data instead.',
            json: $partialResponseText,
        ));
    }

    private function isJsonSchemaResponse(string $responseText) : bool {
        try {
            $decoded = Json::fromPartial($responseText)->toArray();
        } catch (Exception $_) {
            // also covers no JSON at all - which is fine, as some models will respond with text
            return false;
        }
        return isset($decoded['type']) && $decoded['type'] === 'object';
    }

    private function detectNonMatchingJson(bool $check, string $responseText, ResponseModel $responseModel) : Result {
        if (!$check) {
            return Result::success($responseText);
        }
        if ($this->isMatchingResponseModel($responseText, $responseModel)) {
            return Result::success($responseText);
        }
        return Result::failure(new JsonParsingException(
            message: 'JSON does not match schema.',
            json: $responseText,
        ));
    }

    private function isMatchingResponseModel(
        string $partialResponseText,
        ResponseModel $responseModel
    ) : bool {
        $propertyNames = $responseModel->getPropertyNames();
        if (empty($propertyNames)) {
            return true;
        }
        try {
            $decoded = Json::fromPartial($partialResponseText)->toArray();
            // remove last item as it is likely to be incomplete in streaming
            $decoded = Arrays::removeTail($decoded, 1);
        } catch (Exception $_) {
            return false;
        }
        $decodedKeys = array_filter(array_keys($decoded));
        if (empty($decodedKeys)) {
            return true;
        }
        return Arrays::isSubset($decodedKeys, $propertyNames);
    }
}

