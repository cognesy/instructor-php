<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Base;

use Cognesy\Experimental\Pipeline2\Contracts\Execution;
use Cognesy\Experimental\Pipeline2\Contracts\Operator;

/**
 * The default, concrete implementation of the Execution contract.
 *
 * It orchestrates the middleware chain using a recursive-descent pattern,
 * ensuring the terminal function is called at the end.
 *
 * @internal
 */
final class BaseExecution implements Execution
{
    /**
     * @param array<Operator> $operators The ordered list of operators.
     * @param mixed $initialPayload The starting data for the pipeline.
     * @param callable(mixed):mixed $terminal The final operation to call.
     */
    public function __construct(
        private array $operators,
        private mixed $initialPayload,
        private $terminal,
    ) {}

    #[\Override]
    public function run(): mixed {
        return $this->runFrom(0, $this->initialPayload);
    }

    /**
     * The core of the middleware dispatcher. It finds the next applicable
     * operator and executes it, or calls the terminal if none are left.
     */
    public function runFrom(int $index, mixed $payload): mixed {
        // Find the next operator that supports the payload
        $count = count($this->operators);
        for ($i = $index; $i < $count; $i++) {
            $operator = $this->operators[$i];
            if ($operator->supports($payload)) {
                // Create a continuation that will start from the *next* index
                $continuation = new BaseContinuation($this, $i + 1);
                return $operator->handle($payload, $continuation);
            }
        }

        // If no more applicable operators are found, execute the terminal
        return ($this->terminal)($payload);
    }
}
