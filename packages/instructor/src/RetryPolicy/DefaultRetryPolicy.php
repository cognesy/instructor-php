<?php declare(strict_types=1);

namespace Cognesy\Instructor\RetryPolicy;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Instructor\Telemetry\StructuredOutputEventProjector;
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
    private StructuredOutputEventProjector $projector;

    public function __construct(
        CanHandleEvents $events,
    ) {
        $this->projector = new StructuredOutputEventProjector($events);
    }

    #[\Override]
    public function shouldRetry(
        StructuredOutputExecution $execution,
        Result $result,
    ): bool {
        // Retry if not exceeded max attempts
        return !$execution->maxRetriesReached();
    }

    #[\Override]
    public function recordFailure(
        StructuredOutputExecution $execution,
        Result $result,
        InferenceResponse $inference,
    ): StructuredOutputExecution {
        $error = $result->error();
        $errors = is_array($error) ? $error : [$error];

        // Record failed attempt in execution
        $updated = $execution->withFailedAttempt(
            inferenceResponse: $inference,
            errors: $errors,
        );
        // Emit retry event only if another retry is still allowed
        $maxRetries = $updated->config()->maxRetries();
        if ($updated->attemptCount() <= $maxRetries) {
            $this->projector->retryScheduled(
                execution: $updated,
                result: $result,
                retries: $updated->attemptCount(),
            );
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
        Result $result,
    ): mixed {
        if ($result->isSuccess()) {
            return $result->unwrap();
        }

        // Failure - dispatch event and throw
        $errors = $execution->errors();

        $this->projector->recoveryExhausted(
            execution: $execution,
            result: $result,
            retries: $execution->attemptCount(),
        );
        $message = "Structured output recovery attempts limit reached after {$execution->attemptCount()} attempt(s) due to: "
            . implode(", ", array_map(fn($e) => is_string($e) ? $e : (string)$e, $errors));

        throw new StructuredOutputRecoveryException(
            message: $message,
            errors: $errors,
        );
    }
}
