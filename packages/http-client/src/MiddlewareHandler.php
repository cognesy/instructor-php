<?php

namespace Cognesy\Http;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientRequest;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Wraps HTTP driver with a stack of middleware which can pre-
 * or post-process requests and responses. The middleware stack
 * is executed in sequence: the first middleware is called first
 * and handler steps through the chain until the actual HTTP driver
 * is called. Then the response is passed back through the chain
 * in reverse order.
 *
 * Each middleware in the stack can modify the request or response,
 * or short-circuit the chain by returning a response immediately.
 *
 * Middleware can also intercept sync and streamed responses and
 * process them in a custom way.
 */
class MiddlewareHandler implements CanHandleHttpRequest
{
    /**
     * @param CanHandleHttpRequest $driver The actual underlying driver (e.g. GuzzleDriver)
     * @param \Cognesy\Http\Contracts\HttpMiddleware[] $middleware The list of middlewares
     * @param \Cognesy\Events\Dispatchers\EventDispatcher|null $events Event dispatcher for middleware events
     */
    public function __construct(
        protected CanHandleHttpRequest $driver,
        protected array $middleware = [],
        protected ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Run all middlewares in sequence, then the final driver.
     */
    public function handle(HttpClientRequest $request): HttpClientResponse
    {
        // We'll build a chain in reverse so the first middleware is called first.
        $chainedHandler = array_reduce(
            array_reverse($this->middleware),
            function (CanHandleHttpRequest $next, HttpMiddleware $middleware) {
                return $this->makeInstance($middleware, $next);
            },
            $this->driver
        );

        // Now $chainedHandler is a single object implementing CanHandleHttp
        // that internally calls all middlewares, then final driver.
        return $chainedHandler->handle($request);
    }

    /**
     * Create handler instance with next middleware in the chain
     */
    private function makeInstance(HttpMiddleware $middleware, CanHandleHttpRequest $next) : CanHandleHttpRequest {
        return new class($middleware, $next) implements CanHandleHttpRequest {
            public function __construct(
                private HttpMiddleware       $middleware,
                private CanHandleHttpRequest $next
            ) {}

            public function handle(HttpClientRequest $request): HttpClientResponse {
                return $this->middleware->handle($request, $this->next);
            }
        };
    }
}