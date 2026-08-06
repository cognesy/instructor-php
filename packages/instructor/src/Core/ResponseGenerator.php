<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseFailure;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Telemetry\PhaseTelemetryContext;
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
    public function __construct(
        private readonly ResponseMaterializer $materializer,
        private readonly CanExtractResponse $extractor,
    ) {}

    #[\Override]
    public function fromMaterializedInput(
        mixed $input,
        ResponseModel $responseModel,
        ?PhaseTelemetryContext $validationTelemetry = null,
    ) : Result {
        return $this->materializer->materialize($input, $responseModel, $validationTelemetry);
    }

    #[\Override]
    public function fromInferenceResponse(
        InferenceResponse $response,
        ResponseModel $responseModel,
        OutputMode $mode,
        ?PhaseTelemetryContext $extractionTelemetry = null,
        ?PhaseTelemetryContext $validationTelemetry = null,
    ): Result {
        try {
            $data = $this->extractor->extract(ExtractionInput::fromResponse($response, $mode, $extractionTelemetry));
        } catch (Throwable $error) {
            return Result::failure(ResponseFailure::fromError(
                stage: ResponseFailureStage::Extraction,
                error: $error,
                context: ['outputMode' => $mode->value],
            ));
        }

        return $this->materializer->materialize($data, $responseModel, $validationTelemetry);
    }

}
