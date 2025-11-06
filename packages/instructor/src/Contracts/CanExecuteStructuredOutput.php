<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Generator;
use JetBrains\PhpStorm\Deprecated;

/**
 * @deprecated Replaced by CanHandleStructuredOutputAttempts for better separation of concerns.
 * This interface combined streaming and attempt handling, which is now split into:
 * - CanStreamStructuredOutputUpdates (handles streaming chunks)
 * - CanHandleStructuredOutputAttempts (handles validation and retries)
 * Will be removed in future version after all implementations are migrated.
 */
#[Deprecated(
    reason: 'Use CanHandleStructuredOutputAttempts instead',
    replacement: '%class%\\Contracts\\CanHandleStructuredOutputAttempts'
)]
interface CanExecuteStructuredOutput
{
    /**
     * Returns a generator yielding StructuredOutputExecution updates.
     * For sync execution, yields once with final result.
     * For streaming execution, yields multiple partial updates followed by final result.
     *
     * @param StructuredOutputExecution $execution
     * @return Generator<StructuredOutputExecution>
     */
    public function nextUpdate(StructuredOutputExecution $execution): Generator;
}
