<?php

namespace Cognesy\Polyglot\Http;

use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Contracts\HttpMiddleware;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\EventDispatcher;

/**
 * Class MiddlewareHandler
 *
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
class MiddlewareHandler implements CanHandleHttp
{
    /**
     * @param CanHandleHttp $driver The actual underlying driver (e.g. GuzzleDriver)
     * @param HttpMiddleware[] $middleware The list of middlewares
     * @param EventDispatcher|null $events Event dispatcher for middleware events
     */
    public function __construct(
        protected CanHandleHttp $driver,
        protected array $middleware = [],
        protected ?EventDispatcher $events = null,
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
            function (CanHandleHttp $next, HttpMiddleware $middleware) {
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
    private function makeInstance(HttpMiddleware $middleware, CanHandleHttp $next) : CanHandleHttp {
        return new class($middleware, $next) implements CanHandleHttp {
            public function __construct(
                private HttpMiddleware $middleware,
                private CanHandleHttp  $next
            ) {}

            public function handle(HttpClientRequest $request): HttpClientResponse {
                return $this->middleware->handle($request, $this->next);
            }
        };
    }
}