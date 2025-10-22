<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\StructuredOutputRecoveryLimitReached;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;

class RetryHandler
{
    /** @var array<int, string> */
    private array $errors = [];

    public function __construct(
        private CanHandleEvents $events,
    ) {}

    /**
     * Handles validation errors and prepares execution for retry
     */
    public function handleError(
        Result $processingResult,
        StructuredOutputExecution $execution,
        InferenceResponse $inferenceResponse,
        PartialInferenceResponseList $partialResponses
    ): StructuredOutputExecution {
        assert($processingResult instanceof Failure);
        $error = $processingResult->error();
        $this->errors = is_array($error) ? $error : [$error];

        // store failed response
        $execution = $execution->withFailedAttempt(
            inferenceResponse: $inferenceResponse,
            partialInferenceResponses: $partialResponses,
            errors: $this->errors
        );

        if (!$execution->maxRetriesReached()) {
            $this->events->dispatch(new NewValidationRecoveryAttempt(['retries' => $execution->attemptCount(), 'errors' => $this->errors]));
        }

        return $execution;
    }

    /**
     * Finalizes result or throws exception if max retries reached
     */
    public function finalizeOrThrow(
        StructuredOutputExecution $execution,
        Result $processingResult,
    ): mixed {
        if ($processingResult->isFailure()) {
            $this->events->dispatch(new StructuredOutputRecoveryLimitReached(['retries' => $execution->attemptCount(), 'errors' => $this->errors]));
            $message = "Structured output recovery attempts limit reached after {$execution->attemptCount()} attempt(s) due to: " . implode(", ", $this->errors);
            throw new StructuredOutputRecoveryException(
                message: $message,
                errors: $this->errors,
            );
        }
        return $processingResult->unwrap();
    }

    /**
     * Returns accumulated errors from validation attempts
     *
     * @return array<int, string>
     */
    public function errors(): array {
        return $this->errors;
    }
}
