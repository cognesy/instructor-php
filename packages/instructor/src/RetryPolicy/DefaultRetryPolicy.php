<?php declare(strict_types=1);

namespace Cognesy\Instructor\RetryPolicy;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Data\ResponseFailure;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\Attempt\ResponseRecoveryExhausted;
use Cognesy\Instructor\Events\Attempt\ResponseRetryScheduled;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
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
            $this->events->dispatch(new ResponseRetryScheduled($this->canonicalRecoveryPayload(
                execution: $updated,
                phase: 'response.retry_scheduled',
                retries: $updated->attemptCount(),
                result: $result,
            )));
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

        $this->events->dispatch(new ResponseRecoveryExhausted($this->canonicalRecoveryPayload(
            execution: $execution,
            phase: 'response.recovery_exhausted',
            retries: $execution->attemptCount(),
            result: $result,
        )));
        $message = "Structured output recovery attempts limit reached after {$execution->attemptCount()} attempt(s) due to: "
            . implode(", ", array_map(fn($e) => is_string($e) ? $e : (string)$e, $errors));

        throw new StructuredOutputRecoveryException(
            message: $message,
            errors: $errors,
        );
    }

    private function recoveryPayload(
        StructuredOutputExecution $execution,
        string $phase,
        int $retries,
        array $errors = [],
    ) : array {
        $requestId = $execution->request()->id()->toString();
        $executionId = $execution->id()->toString();
        $attemptId = $execution->lastFinalizedAttempt()?->id()->toString()
            ?? $execution->activeAttempt()?->id()->toString();

        return array_filter([
            'requestId' => $requestId,
            'executionId' => $executionId,
            'attemptId' => $attemptId,
            'phase' => $phase,
            'phaseId' => $this->phaseId($executionId, $phase, $attemptId),
            'retries' => $retries,
            'errors' => $errors,
        ], fn(mixed $value): bool => $value !== null);
    }

    private function canonicalRecoveryPayload(
        StructuredOutputExecution $execution,
        string $phase,
        int $retries,
        Result $result,
    ): array {
        $failure = $result->error();
        $failureData = match (true) {
            $failure instanceof ResponseFailure => $failure->eventData(),
            default => [
                'errorMessage' => 'Structured output recovery failed.',
                'errorType' => get_debug_type($failure),
            ],
        };

        return [
            ...$this->recoveryPayload(
                execution: $execution,
                phase: $phase,
                retries: $retries,
                errors: [],
            ),
            ...$failureData,
        ];
    }

    private function phaseId(string $executionId, string $phase, ?string $attemptId = null) : string
    {
        return match ($attemptId) {
            null => "{$executionId}:{$phase}",
            default => "{$executionId}:{$phase}:{$attemptId}",
        };
    }
}
