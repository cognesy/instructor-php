<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

/**
 * Manages a stack of middleware for MessageChain processing.
 *
 * The middleware stack implements the classic middleware pattern where each
 * middleware can decide whether to continue processing and can modify the
 * state before and after the next middleware executes.
 */
final class PipelineMiddlewareStack
{
    /** @var CanControlStateProcessing[] */
    private array $middleware = [];

    public function add(CanControlStateProcessing ...$middleware): self {
        array_push($this->middleware, ...$middleware);
        return $this;
    }

    public function prepend(CanControlStateProcessing ...$middleware): self {
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
     * @param callable(ProcessingState):ProcessingState $finalProcessor Final processor to execute after all middleware
     */
    public function process(ProcessingState $state, callable $finalProcessor): ProcessingState {
        if (empty($this->middleware)) {
            return $finalProcessor($state);
        }
        
        $stack = $finalProcessor;
        
        // Build stack from last to first middleware
        for ($i = count($this->middleware) - 1; $i >= 0; $i--) {
            $middleware = $this->middleware[$i];
            $stack = function(ProcessingState $s) use ($middleware, $stack) {
                return $middleware->handle($s, $stack);
            };
        }
        
        return $stack($state);
    }

    public function with(CanControlStateProcessing ...$middleware): self {
        $new = clone $this;
        $new->add(...$middleware);
        return $new;
    }

    public function clear(): self {
        $this->middleware = [];
        return $this;
    }

    /**
     * @return CanControlStateProcessing[]
     */
    public function all(): array {
        return $this->middleware;
    }
}