<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseFailure;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Events\Response\ResponseMaterializationFailed;
use Cognesy\Instructor\Events\Response\ResponseMaterialized;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

final readonly class AttemptProcessor
{
    public function __construct(
        private CanGenerateResponse $responseGenerator,
        private CanDetermineRetry $retryPolicy,
        private EventDispatcherInterface $events,
    ) {}

    public function process(
        StructuredOutputExecution $execution,
        InferenceResponse $inferenceResponse,
        mixed $materializationInput = null,
    ): AttemptProcessingResult {
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        $materializationResult = $this->responseGenerator->makeResponse(
            $inferenceResponse,
            $responseModel,
            $execution->outputMode(),
            $materializationInput,
        );
        $this->reportMaterialization($execution, $materializationResult);

        if ($materializationResult->isSuccess()) {
            $finalValue = $materializationResult->unwrap();
            $completed = $execution->withSuccessfulAttempt(
                inferenceResponse: $inferenceResponse,
                returnedValue: $finalValue,
            );
            $response = $completed->inferenceResponse();
            assert($response !== null, 'Successful attempt must produce a finalized inference response');

            return AttemptProcessingResult::terminal(
                execution: $completed,
                response: StructuredOutputResponse::final(
                    value: $finalValue,
                    inferenceResponse: $response,
                ),
            );
        }

        $failed = $this->retryPolicy->recordFailure(
            $execution,
            $materializationResult,
            $inferenceResponse,
        );

        if ($this->retryPolicy->shouldRetry($failed, $materializationResult)) {
            return AttemptProcessingResult::retry(
                execution: $this->retryPolicy->prepareRetry($failed),
            );
        }

        $this->retryPolicy->finalizeOrThrow($failed, $materializationResult);
        $response = $failed->inferenceResponse() ?? $inferenceResponse;

        return AttemptProcessingResult::terminal(
            execution: $failed,
            response: StructuredOutputResponse::final(
                value: $failed->output(),
                inferenceResponse: $response,
            ),
        );
    }

    private function reportMaterialization(
        StructuredOutputExecution $execution,
        Result $result,
    ): void {
        $correlation = $this->correlationPayload($execution);
        if ($result->isSuccess()) {
            $this->events->dispatch(new ResponseMaterialized([
                ...$correlation,
                ...$this->valueSummary($result->unwrap()),
            ]));
            return;
        }

        $error = $result->error();
        $failure = match (true) {
            $error instanceof ResponseFailure => $error->eventData(),
            default => [
                'errorMessage' => 'Structured output materialization failed.',
                'errorType' => $error instanceof Throwable ? $error::class : get_debug_type($error),
            ],
        };
        $this->events->dispatch(new ResponseMaterializationFailed([
            ...$correlation,
            ...$failure,
        ]));
    }

    /** @return array<string, string> */
    private function correlationPayload(StructuredOutputExecution $execution): array
    {
        $executionId = $execution->id()->toString();
        $attemptId = $execution->activeAttempt()?->id()->toString() ?? 'unknown';
        $phase = 'response.materialization';

        return [
            'requestId' => $execution->request()->id()->toString(),
            'executionId' => $executionId,
            'attemptId' => $attemptId,
            'phase' => $phase,
            'phaseId' => "{$executionId}:{$phase}:{$attemptId}",
        ];
    }

    /** @return array<string, mixed> */
    private function valueSummary(mixed $value): array
    {
        return match (true) {
            is_object($value) => [
                'resultType' => $value::class,
                'fieldCount' => count(get_object_vars($value)),
            ],
            is_array($value) => [
                'resultType' => 'array',
                'itemCount' => count($value),
                'keys' => array_slice(array_keys($value), 0, 20),
            ],
            default => ['resultType' => get_debug_type($value)],
        };
    }
}
