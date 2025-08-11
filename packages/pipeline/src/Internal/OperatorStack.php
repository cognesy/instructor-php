<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Internal;

use ArrayIterator;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
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
final class OperatorStack implements CanProcessState, Countable, IteratorAggregate
{
    /** @var CanProcessState[] */
    private array $middleware = [];

    public function add(CanProcessState ...$middleware): self {
        array_push($this->middleware, ...$middleware);
        return $this;
    }

    public function prepend(CanProcessState ...$middleware): self {
        array_unshift($this->middleware, ...$middleware);
        return $this;
    }

    public function isEmpty(): bool {
        return empty($this->middleware);
    }

    public function count(): int {
        return count($this->middleware);
    }

    /**
     * @param ?callable(ProcessingState):ProcessingState $next Final processor to execute after all middleware
     */
    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        if (empty($this->middleware)) {
            return match(true) {
                ($next === null) => $state,
                default => $next($state),
            };
        }
        
        $stack = $next ?? fn(ProcessingState $s) => $s;
        // Build stack from last to first middleware
        for ($i = count($this->middleware) - 1; $i >= 0; $i--) {
            $middleware = $this->middleware[$i];
            $stack = function(ProcessingState $s) use ($middleware, $stack) {
                return $middleware->process($s, $stack);
            };
        }
        
        return $stack($state);
    }

    public function with(CanProcessState ...$middleware): self {
        $new = clone $this;
        $new->add(...$middleware);
        return $new;
    }

    public function clear(): self {
        $this->middleware = [];
        return $this;
    }

    /**
     * @return CanProcessState[]
     */
    public function all(): array {
        return $this->middleware;
    }

    /**
     * @return Iterator<CanProcessState>
     */
    public function getIterator(): Iterator {
        return new ArrayIterator($this->middleware);
    }
}