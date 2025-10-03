<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Generator;

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
