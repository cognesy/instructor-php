<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Psr\EventDispatcher\EventDispatcherInterface;

class MiddlewareStack
{
    private EventDispatcherInterface $events;
    /** @var HttpMiddleware[] */
    private array $stack;

    public function __construct(
        EventDispatcherInterface $events,
        array $middlewares = [],
    ) {
        $this->events = $events;
        $this->stack = $middlewares;
    }

    /**
     * Adds middleware to the stack
     *
     * @param HttpMiddleware $middleware The middleware to add
     * @param string|null $name Optional name for the middleware
     * @return self
     */
    public function append(HttpMiddleware $middleware, ?string $name = null): self {
        $nextStack = $this->stack;
        match (true) {
            $name !== null => $nextStack[$name] = $middleware,
            default => $nextStack[] = $middleware,
        };
        return new self($this->events, $nextStack);
    }

    /**
     * Adds multiple middleware to the stack
     *
     * @param HttpMiddleware[] $middlewares Array of middleware to add
     * @return self
     */
    public function appendMany(array $middlewares): self {
        $nextStack = $this->stack;
        foreach ($middlewares as $key => $middleware) {
            match (true) {
                is_string($key) => $nextStack[$key] = $middleware,
                default => $nextStack[] = $middleware,
            };
        }
        return new self($this->events, $nextStack);
    }

    /**
     * Prepends middleware to the start of the stack
     *
     * @param HttpMiddleware $middleware The middleware to prepend
     * @param string|null $name Optional name for the middleware
     * @return self
     */
    public function prepend(HttpMiddleware $middleware, ?string $name = null): self {
        $nextStack = match (true) {
            $name !== null => [$name => $middleware] + $this->stack,
            default => [$middleware, ...$this->stack],
        };
        return new self($this->events, $nextStack);
    }

    /**
     * Prepends multiple middleware to the start of the stack
     *
     * @param HttpMiddleware[] $middlewares Array of middleware to prepend
     * @return self
     */
    public function prependMany(array $middlewares): self {
        $nextStack = $this->stack;
        foreach (array_reverse($middlewares, true) as $key => $middleware) {
            $nextStack = match (true) {
                is_string($key) => [$key => $middleware] + $nextStack,
                default => [$middleware, ...$nextStack],
            };
        }
        return new self($this->events, $nextStack);
    }

    /**
     * Remove a middleware by its name
     *
     * @param string $name The name of the middleware to remove
     * @return self
     */
    public function remove(string $name): self {
        $nextStack = $this->stack;
        if (isset($nextStack[$name])) {
            unset($nextStack[$name]);
        }
        return new self($this->events, $nextStack);
    }

    /**
     * Replace a middleware with a new one
     *
     * @param string $name The name of the middleware to replace
     * @param HttpMiddleware $middleware The new middleware
     * @return self
     */
    public function replace(string $name, HttpMiddleware $middleware): self {
        $nextStack = $this->stack;
        $nextStack[$name] = $middleware;
        return new self($this->events, $nextStack);
    }

    /**
     * Clear all middleware from the stack
     *
     * @return self
     */
    public function clear(): self {
        return new self($this->events);
    }

    /**
     * Get all middleware in the stack
     *
     * @return HttpMiddleware[]
     */
    public function all(): array {
        return $this->stack;
    }

    /**
     * Check if middleware exists by name
     *
     * @param string $name The name to check
     * @return bool
     */
    public function has(string $name): bool {
        return isset($this->stack[$name]);
    }

    /**
     * Get a middleware by name or index
     *
     * @param string|int $key The name or index of the middleware
     * @return HttpMiddleware|null
     */
    public function get(string|int $key): ?HttpMiddleware {
        return $this->stack[$key] ?? null;
    }

    /**
     * Filter the middleware stack based on a condition
     *
     * @param callable(HttpMiddleware, int): bool $callback Function that takes HttpMiddleware and returns bool
     * @return self
     */
    public function filter(callable $callback): self {
        $nextStack = array_filter($this->stack, $callback, ARRAY_FILTER_USE_BOTH);
        return new self($this->events, $nextStack);
    }

    /**
     * Decorate a driver with the middleware stack
     *
     * @param CanHandleHttpRequest $driver The HTTP driver to decorate
     * @return CanHandleHttpRequest The decorated driver
     */
    public function decorate(CanHandleHttpRequest $driver): CanHandleHttpRequest {
        if (empty($this->stack)) {
            return $driver;
        }
        return new MiddlewareHandler($driver, array_values($this->stack), $this->events);
    }

    public function toDebugArray(): array {
        return array_map(
            function ($middleware, $key) {
                return [
                    'name' => $key,
                    'class' => get_class($middleware),
                ];
            },
            $this->stack,
            array_keys($this->stack),
        );
    }
}
