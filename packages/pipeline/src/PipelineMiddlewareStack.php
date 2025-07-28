<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

/**
 * Manages a stack of middleware for MessageChain processing.
 *
 * The middleware stack implements the classic middleware pattern where each
 * middleware can decide whether to continue processing and can modify the
 * envelope before and after the next middleware executes.
 *
 * Example:
 * ```php
 * $stack = new MiddlewareStack();
 * $stack->add(new LoggingMiddleware());
 * $stack->add(new MetricsMiddleware());
 * $stack->add(new TracingMiddleware());
 *
 * $result = $stack->process($envelope, $finalProcessor);
 * ```
 */
class PipelineMiddlewareStack
{
    /** @var PipelineMiddlewareInterface[] */
    private array $middleware = [];

    /**
     * Add middleware to the stack.
     *
     * Middleware is executed in the order it's added.
     */
    public function add(PipelineMiddlewareInterface ...$middleware): self {
        $this->middleware = [...$this->middleware, ...$middleware];
        return $this;
    }

    /**
     * Add middleware at the beginning of the stack.
     *
     * This middleware will execute before any previously added middleware.
     */
    public function prepend(PipelineMiddlewareInterface ...$middleware): self {
        $this->middleware = [...$middleware, ...$this->middleware];
        return $this;
    }

    /**
     * Check if stack has any middleware.
     */
    public function isEmpty(): bool {
        return empty($this->middleware);
    }

    /**
     * Get count of middleware in stack.
     */
    public function count(): int {
        return count($this->middleware);
    }

    /**
     * Process envelope through all middleware, ending with final processor.
     *
     * @param Envelope $envelope Initial envelope
     * @param callable $finalProcessor Final processor to execute after all middleware
     * @return Envelope Processed envelope
     */
    public function process(Envelope $envelope, callable $finalProcessor): Envelope {
        if (empty($this->middleware)) {
            return $finalProcessor($envelope);
        }

        // Build the middleware chain using array_reduce
        // We reverse the middleware array so they execute in the correct order
        $stack = array_reduce(
            array_reverse($this->middleware),
            function (callable $next, PipelineMiddlewareInterface $middleware) {
                return fn(Envelope $envelope) => $middleware->handle($envelope, $next);
            },
            $finalProcessor,
        );

        return $stack($envelope);
    }

    /**
     * Create a new stack with additional middleware.
     */
    public function with(PipelineMiddlewareInterface ...$middleware): self {
        $new = clone $this;
        $new->add(...$middleware);
        return $new;
    }

    /**
     * Clear all middleware from the stack.
     */
    public function clear(): self {
        $this->middleware = [];
        return $this;
    }

    /**
     * Get all middleware in the stack.
     *
     * @return PipelineMiddlewareInterface[]
     */
    public function all(): array {
        return $this->middleware;
    }
}