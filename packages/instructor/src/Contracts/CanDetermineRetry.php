<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Utils\Result\Result;

/**
 * Domain policy for determining retry behavior after validation failures.
 *
 * DDD: This is a POLICY object that encapsulates business rules for retries.
 * Implementations can vary: simple max retries, exponential backoff, prompt modification, etc.
 *
 * Responsibility: Decide whether to retry, record failures, prepare retries, and finalize results.
 */
interface CanDetermineRetry
{
    /**
     * Determine if execution should retry after validation failure.
     *
     * @param StructuredOutputExecution $execution Current execution state
     * @param Result $validationResult Failed validation result
     * @return bool True if should retry, false if should fail
     */
    public function shouldRetry(
        StructuredOutputExecution $execution,
        Result $validationResult,
    ): bool;

    /**
     * Record a failed attempt in execution and return updated execution.
     * Updates execution with error details and increments attempt count.
     *
     * @param StructuredOutputExecution $execution Current execution
     * @param Result $validationResult Failed validation
     * @param InferenceResponse $inference Final inference from failed attempt
     * @param PartialInferenceResponse $partial Partial accumulated during attempt
     * @return StructuredOutputExecution Updated execution with failed attempt recorded
     */
    public function recordFailure(
        StructuredOutputExecution $execution,
        Result $validationResult,
        InferenceResponse $inference,
        PartialInferenceResponse $partial,
    ): StructuredOutputExecution;

    /**
     * Prepare execution for next retry attempt.
     * Can modify request (e.g., adjust prompt, temperature) for retry.
     *
     * @param StructuredOutputExecution $execution Execution with recorded failure
     * @return StructuredOutputExecution Modified execution for retry
     */
    public function prepareRetry(
        StructuredOutputExecution $execution,
    ): StructuredOutputExecution;

    /**
     * Finalize execution or throw exception based on validation result.
     * Called when no more retries are possible.
     *
     * @param StructuredOutputExecution $execution Final execution state
     * @param Result $validationResult Last validation result
     * @return mixed Unwrapped value if success
     * @throws \Exception If validation failed and max retries exceeded
     */
    public function finalizeOrThrow(
        StructuredOutputExecution $execution,
        Result $validationResult,
    ): mixed;
}
