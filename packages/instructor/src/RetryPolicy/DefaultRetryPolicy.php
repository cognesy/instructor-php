<?php declare(strict_types=1);

namespace Cognesy\Instructor\RetryPolicy;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\StructuredOutputRecoveryLimitReached;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Result\Result;

/**
 * Default retry policy: simple max retries with error accumulation.
 *
 * DDD: This is a POLICY object - encapsulates business rules for retries.
 * Stateless: All state is stored in StructuredOutputExecution.
 */
final readonly class DefaultRetryPolicy implements CanDetermineRetry
{
    public function __construct(
        private CanHandleEvents $events,
    ) {}

    #[\Override]
    public function shouldRetry(
        StructuredOutputExecution $execution,
        Result $validationResult,
    ): bool {
        // Retry if not exceeded max attempts
        return !$execution->maxRetriesReached();
    }

    #[\Override]
    public function recordFailure(
        StructuredOutputExecution $execution,
        Result $validationResult,
        InferenceResponse $inference,
        PartialInferenceResponseList $partials,
    ): StructuredOutputExecution {
        $error = $validationResult->error();
        $errors = is_array($error) ? $error : [$error];

        // Record failed attempt in execution
        $updated = $execution->withFailedAttempt(
            inferenceResponse: $inference,
            partialInferenceResponses: $partials,
            errors: $errors,
        );
        // Emit retry event only if another retry is still allowed
        $maxRetries = $updated->config()->maxRetries();
        if ($updated->attemptCount() <= $maxRetries) {
            $this->events->dispatch(new NewValidationRecoveryAttempt([
                'retries' => $updated->attemptCount(),
                'errors' => $updated->currentErrors(),
            ]));
        }

        return $updated;
    }

    #[\Override]
    public function prepareRetry(
        StructuredOutputExecution $execution,
    ): StructuredOutputExecution {
        // Default: no modifications for retry – subclasses could adjust prompt, temperature, etc.
        return $execution;
    }

    #[\Override]
    public function finalizeOrThrow(
        StructuredOutputExecution $execution,
        Result $validationResult,
    ): mixed {
        if ($validationResult->isSuccess()) {
            return $validationResult->unwrap();
        }

        // Failure - dispatch event and throw
        $errors = $execution->errors();

        $this->events->dispatch(new StructuredOutputRecoveryLimitReached([
            'retries' => $execution->attemptCount(),
            'errors' => $errors,
        ]));

        $message = "Structured output recovery attempts limit reached after {$execution->attemptCount()} attempt(s) due to: "
            . implode(", ", array_map(fn($e) => is_string($e) ? $e : (string)$e, $errors));

        throw new StructuredOutputRecoveryException(
            message: $message,
            errors: $errors,
        );
    }
}
