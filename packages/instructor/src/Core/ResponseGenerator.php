<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseFailure;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Enums\ResponseFailureStage;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Turns a complete InferenceResponse into the response value:
 * extract (mode-specific output → array), then materialize. ResponseMaterializer
 * owns schema validation → deserialization → object validation → transformation.
 * This class owns extraction only. AttemptProcessor reports the final
 * materialization outcome where request/execution/attempt IDs are available.
 */
class ResponseGenerator implements CanGenerateResponse
{
    private readonly ResponseMaterializer $materializer;

    public function __construct(
        CanDeserializeResponse $responseDeserializer,
        CanValidateResponse $responseValidator,
        CanTransformResponse $responseTransformer,
        private readonly CanExtractResponse $extractor,
    ) {
        $this->materializer = new ResponseMaterializer(
            deserializer: $responseDeserializer,
            validator: $responseValidator,
            transformer: $responseTransformer,
        );
    }

    #[\Override]
    public function makeResponse(
        InferenceResponse $response,
        ResponseModel $responseModel,
        OutputMode $mode,
        mixed $materializationInput = null,
    ) : Result {
        $result = match (true) {
            $materializationInput !== null => $this->materializer->materialize($materializationInput, $responseModel),
            default => $this->extractAndMaterialize($response, $responseModel, $mode),
        };

        return $result;
    }

    public function materializer(): ResponseMaterializer {
        return $this->materializer;
    }

    private function extractAndMaterialize(
        InferenceResponse $response,
        ResponseModel $responseModel,
        OutputMode $mode,
    ): Result {
        try {
            $data = $this->extractor->extract(ExtractionInput::fromResponse($response, $mode));
        } catch (Throwable $error) {
            return Result::failure(ResponseFailure::fromError(
                stage: ResponseFailureStage::Extraction,
                error: $error,
                context: ['outputMode' => $mode->value],
            ));
        }

        return $this->materializer->materialize($data, $responseModel);
    }

}
