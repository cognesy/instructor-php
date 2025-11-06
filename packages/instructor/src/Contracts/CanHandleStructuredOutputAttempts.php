<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;

/**
 * Orchestrates full structured-output attempts: stream chunks, validate,
 * and apply retry policy on failures until success or terminal error.
 */
interface CanHandleStructuredOutputAttempts
{
    public function hasNext(StructuredOutputExecution $execution): bool;
    public function nextUpdate(StructuredOutputExecution $execution): StructuredOutputExecution;
}

