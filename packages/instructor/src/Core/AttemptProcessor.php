<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

final readonly class AttemptProcessor
{
    public function __construct(
        private CanGenerateResponse $responseGenerator,
        private CanDetermineRetry $retryPolicy,
    ) {}

    public function process(
        StructuredOutputExecution $execution,
        InferenceResponse $inferenceResponse,
        mixed $prebuiltValue = null,
    ): AttemptProcessingResult {
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        $validationResult = $this->responseGenerator->makeResponse(
            $inferenceResponse,
            $responseModel,
            $execution->outputMode(),
            $prebuiltValue,
        );

        if ($validationResult->isSuccess()) {
            $finalValue = $validationResult->unwrap();
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
                    rawResponse: $response,
                ),
            );
        }

        $failed = $this->retryPolicy->recordFailure(
            $execution,
            $validationResult,
            $inferenceResponse,
        );

        if ($this->retryPolicy->shouldRetry($failed, $validationResult)) {
            return AttemptProcessingResult::retry(
                execution: $this->retryPolicy->prepareRetry($failed),
            );
        }

        $this->retryPolicy->finalizeOrThrow($failed, $validationResult);
        $response = $failed->inferenceResponse() ?? $inferenceResponse;

        return AttemptProcessingResult::terminal(
            execution: $failed,
            response: StructuredOutputResponse::final(
                value: $failed->output(),
                rawResponse: $response,
            ),
        );
    }
}
