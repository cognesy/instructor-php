<?php

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Utils\Events\EventDispatcher;

class MiddlewareStack
{
    /** @var HttpMiddleware[] */
    private array $stack = [];

    public function __construct(
        private ?EventDispatcher $events = null
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Adds middleware to the stack
     *
     * @param \Cognesy\Http\Contracts\HttpMiddleware $middleware The middleware to add
     * @param string|null $name Optional name for the middleware
     * @return self
     */
    public function append(HttpMiddleware $middleware, ?string $name = null): self
    {
        if ($name !== null) {
            $this->stack[$name] = $middleware;
        } else {
            $this->stack[] = $middleware;
        }
        return $this;
    }

    /**
     * Adds multiple middleware to the stack
     *
     * @param \Cognesy\Http\Contracts\HttpMiddleware[] $middlewares Array of middleware to add
     * @return self
     */
    public function appendMany(array $middlewares): self
    {
        foreach ($middlewares as $key => $middleware) {
            if (is_string($key)) {
                $this->append($middleware, $key);
            } else {
                $this->append($middleware);
            }
        }
        return $this;
    }

    /**
     * Prepends middleware to the start of the stack
     *
     * @param HttpMiddleware $middleware The middleware to prepend
     * @param string|null $name Optional name for the middleware
     * @return self
     */
    public function prepend(HttpMiddleware $middleware, ?string $name = null): self
    {
        if ($name !== null) {
            $this->stack = [$name => $middleware] + $this->stack; // Preserve associative keys
        } else {
            array_unshift($this->stack, $middleware); // Add to start for unnamed
        }
        return $this;
    }

    /**
     * Prepends multiple middleware to the start of the stack
     *
     * @param HttpMiddleware[] $middlewares Array of middleware to prepend
     * @return self
     */
    public function prependMany(array $middlewares): self
    {
        foreach (array_reverse($middlewares) as $key => $middleware) {
            if (is_string($key)) {
                $this->prepend($middleware, $key);
            } else {
                $this->prepend($middleware);
            }
        }
        return $this;
    }

    /**
     * Remove a middleware by its name
     *
     * @param string $name The name of the middleware to remove
     * @return self
     */
    public function remove(string $name): self
    {
        if (isset($this->stack[$name])) {
            unset($this->stack[$name]);
        }
        return $this;
    }

    /**
     * Replace a middleware with a new one
     *
     * @param string $name The name of the middleware to replace
     * @param \Cognesy\Http\Contracts\HttpMiddleware $middleware The new middleware
     * @return self
     */
    public function replace(string $name, HttpMiddleware $middleware): self
    {
        $this->stack[$name] = $middleware;
        return $this;
    }

    /**
     * Clear all middleware from the stack
     *
     * @return self
     */
    public function clear(): self
    {
        $this->stack = [];
        return $this;
    }

    /**
     * Get all middleware in the stack
     *
     * @return HttpMiddleware[]
     */
    public function all(): array
    {
        return $this->stack;
    }

    /**
     * Check if middleware exists by name
     *
     * @param string $name The name to check
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->stack[$name]);
    }

    /**
     * Get a middleware by name or index
     *
     * @param string|int $key The name or index of the middleware
     * @return \Cognesy\Http\Contracts\HttpMiddleware|null
     */
    public function get(string|int $key): ?HttpMiddleware
    {
        return $this->stack[$key] ?? null;
    }

    /**
     * Filter the middleware stack based on a condition
     *
     * @param callable $callback Function that takes HttpMiddleware and returns bool
     * @return self
     */
    public function filter(callable $callback): self
    {
        $this->stack = array_filter($this->stack, $callback, ARRAY_FILTER_USE_BOTH);
        return $this;
    }

    /**
     * Decorate a driver with the middleware stack
     *
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $driver The HTTP driver to decorate
     * @return \Cognesy\Http\Contracts\CanHandleHttpRequest The decorated driver
     */
    public function decorate(CanHandleHttpRequest $driver): CanHandleHttpRequest
    {
        if (empty($this->stack)) {
            return $driver;
        }

        return new MiddlewareHandler($driver, array_values($this->stack), $this->events);
    }
}