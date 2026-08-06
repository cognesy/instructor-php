<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Telemetry\PhaseTelemetryContext;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

/**
 * Produces the final response value. The two methods are the two genuinely different
 * routes to it, and callers pick one explicitly: the sync path always has an inference
 * response to extract from, while the streaming path may already hold a value the
 * aggregator built out of the deltas.
 */
interface CanGenerateResponse
{
    /**
     * Extracts the mode-specific payload out of the inference response, then materializes it.
     *
     * The two contexts are supplied by callers that hold a structured-output execution, so the
     * extraction and validation lifecycle events can be stamped as children of its root. Both
     * stages are context-free and work fine without them.
     */
    public function fromInferenceResponse(
        InferenceResponse $response,
        ResponseModel $responseModel,
        OutputMode $mode,
        ?PhaseTelemetryContext $extractionTelemetry = null,
        ?PhaseTelemetryContext $validationTelemetry = null,
    ) : Result;

    /**
     * Materializes an already-extracted value. `$input` is whatever the stream aggregator
     * accumulated, so it is as loosely typed as the response model allows. There is no
     * extraction context here because this route skips extraction entirely.
     */
    public function fromMaterializedInput(
        mixed $input,
        ResponseModel $responseModel,
        ?PhaseTelemetryContext $validationTelemetry = null,
    ) : Result;
}
