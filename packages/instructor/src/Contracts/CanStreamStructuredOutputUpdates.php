<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;

/**
 * Processes streamed chunks within a single attempt and returns updated execution snapshots.
 * Implementations must NOT perform validation or retries.
 */
interface CanStreamStructuredOutputUpdates
{
    public function hasNext(StructuredOutputExecution $execution): bool;
    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution;
}

