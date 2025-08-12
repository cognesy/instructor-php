<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Internal;

use ArrayIterator;
use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Countable;
use Iterator;
use IteratorAggregate;

/**
 * Manages a stack of middleware for MessageChain processing.
 *
 * The middleware stack implements the classic middleware pattern where each
 * middleware can decide whether to continue processing and can modify the
 * state before and after the next middleware executes.
 */
final class OperatorStack implements Countable, IteratorAggregate
{
    /** @var CanProcessState[] */
    private array $operators = [];

    public function add(CanProcessState ...$operators): self {
        array_push($this->operators, ...$operators);
        return $this;
    }

    public function prepend(CanProcessState ...$operators): self {
        array_unshift($this->operators, ...$operators);
        return $this;
    }

    public function isEmpty(): bool {
        return empty($this->operators);
    }

    public function count(): int {
        return count($this->operators);
    }

    public function with(CanProcessState ...$operators): self {
        $new = clone $this;
        $new->add(...$operators);
        return $new;
    }

    public function clear(): self {
        $this->operators = [];
        return $this;
    }

    /**
     * @return CanProcessState[]
     */
    public function all(): array {
        return $this->operators;
    }

    /**
     * @return Iterator<CanProcessState>
     */
    public function getIterator(): Iterator {
        return new ArrayIterator($this->operators);
    }

    /**
     * Builds the middleware stack by wrapping each operator in a closure that
     * calls the next operator in the stack.
     *
     * @param ?callable(CanCarryState):CanCarryState $next Final processor to execute after all middleware
     */
    public function callStack(callable $next): callable {
        $stack = $next ?? fn(CanCarryState $s) => $s;
        // Build stack from last to first middleware
        for ($i = count($this->operators) - 1; $i >= 0; $i--) {
            $operator = $this->operators[$i];
            $stack = static function(CanCarryState $s) use ($operator, $stack) : CanCarryState {
                return $operator->process($s, $stack);
            };
        }
        return $stack;
    }
}