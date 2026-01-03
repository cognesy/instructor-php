<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation;

use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateContracts\CanCarryState;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Json\JsonParsingException;
use Cognesy\Utils\Result\Result;

/**
 * Validates partial response text during streaming according to early policies:
 * - prevent JSON Schema-shaped responses when strict JSON object data is expected
 * - ensure keys match expected ResponseModel (subset check, tolerant for partials)
 */
class PartialValidation implements CanValidatePartialResponse
{
    private PartialsGeneratorConfig $config;

    public function __construct(
        PartialsGeneratorConfig $config
    ) {
        $this->config = $config;
    }

    #[\Override]
    public function validatePartialResponse(
        array $data,
        ResponseModel $responseModel,
    ) : Result {
        return $this->makePartialValidationPipeline(
                $data,
                $responseModel,
                $this->config,
            )
            ->executeWith(ProcessingState::with($data))
            ->result();
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function makePartialValidationPipeline(
        array $data,
        ResponseModel $responseModel,
        PartialsGeneratorConfig $config,
    ) : Pipeline {
        return Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn(array $d) => $this->preventJsonSchemaResponse($config->preventJsonSchema, $d))
            ->through(fn(array $d) => $this->detectNonMatchingJson($config->matchToExpectedFields, $d, $responseModel))
            ->onFailure(fn(CanCarryState $state) => throw new JsonParsingException(
                message: (string) $state->result()->error(),
                json: json_encode($data),
            ))
            ->create();
    }

    private function preventJsonSchemaResponse(bool $check, array $data) : Result {
        if (!$check) {
            return Result::success($data);
        }
        if (!$this->isJsonSchemaResponse($data)) {
            return Result::success($data);
        }
        return Result::failure(new JsonParsingException(
            message: 'You started responding with JSONSchema. Respond correctly with strict JSON object data instead.',
            json: json_encode($data),
        ));
    }

    private function isJsonSchemaResponse(array $data) : bool {
        return isset($data['type']) && $data['type'] === 'object';
    }

    private function detectNonMatchingJson(bool $check, array $data, ResponseModel $responseModel) : Result {
        if (!$check) {
            return Result::success($data);
        }
        if ($this->isMatchingResponseModel($data, $responseModel)) {
            return Result::success($data);
        }
        return Result::failure(new JsonParsingException(
            message: 'JSON does not match schema.',
            json: json_encode($data),
        ));
    }

    private function isMatchingResponseModel(
        array $data,
        ResponseModel $responseModel
    ) : bool {
        $propertyNames = $responseModel->getPropertyNames();
        if (empty($propertyNames)) {
            return true;
        }

        // remove last item as it is likely to be incomplete in streaming
        $data = Arrays::removeTail($data, 1);
        
        $decodedKeys = array_filter(array_keys($data));
        if (empty($decodedKeys)) {
            return true;
        }
        return Arrays::isSubset($decodedKeys, $propertyNames);
    }
}