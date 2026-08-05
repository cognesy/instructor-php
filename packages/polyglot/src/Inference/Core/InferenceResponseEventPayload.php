<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

/**
 * The single builder for the InferenceResponseCreated payload.
 *
 * WHY THIS EXISTS. The sync path (BaseInferenceRequestDriver) and the stream path
 * (InferenceStream) each had their own near-identical copy, and they had already
 * drifted: the stream emitted `executionId`, the sync path did not, so a consumer
 * correlating on it silently got nothing from non-streamed responses. Both copies
 * are gone; both paths call this.
 *
 * ON THE NULLABLE EXECUTION ID. InferenceStream is constructed with an execution and
 * passes its id. BaseInferenceRequestDriver passes null, and this is not an oversight
 * to be tidied away later: InferenceRuntime holds ONE driver and creates a NEW
 * execution per create(), so a driver instance serves many executions and has no
 * truthful id to give. The key is emitted either way so that the key set does not
 * depend on which path produced the event. Correlate a sync response to its execution
 * via `requestId`, which InferenceStarted and InferenceCompleted also carry.
 *
 * `statusCode` is conditional on the response carrying HTTP data. That was never part
 * of the drift -- both copies applied the same condition -- and it varies with the
 * data, not the path.
 */
final class InferenceResponseEventPayload
{
    /**
     * @return array<string,mixed>
     */
    public static function build(
        InferenceResponse $response,
        InferenceRequest $request,
        ?string $executionId,
    ): array {
        $payload = [
            'executionId' => $executionId,
            'requestId' => $request->id()->toString(),
            'model' => $request->model(),
            'responseId' => $response->id->toString(),
            'finishReason' => $response->finishReason()->value,
            'contentLength' => strlen($response->content()),
            'reasoningContentLength' => strlen($response->reasoningContent()),
            'hasToolCalls' => $response->hasToolCalls(),
            'toolCallCount' => $response->toolCalls()->count(),
            'usage' => $response->usage()->toArray(),
            'isPartial' => $response->isPartial(),
        ];

        $statusCode = $response->responseData()->statusCode();
        if ($statusCode > 0) {
            $payload['statusCode'] = $statusCode;
        }

        return $payload;
    }
}
