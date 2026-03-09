<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

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
    private readonly CanHandleHttpRequest $handler;

    /** @param HttpMiddleware[] $middleware */
    public function __construct(
        CanHandleHttpRequest $driver,
        array $middleware = [],
    ) {
        $this->handler = array_reduce(
            array_reverse($middleware),
            fn(CanHandleHttpRequest $next, HttpMiddleware $middleware): CanHandleHttpRequest => $this->wrap($middleware, $next),
            $driver,
        );
    }

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse {
        return $this->handler->handle($request);
    }

    private function wrap(HttpMiddleware $middleware, CanHandleHttpRequest $next): CanHandleHttpRequest {
        return new class($middleware, $next) implements CanHandleHttpRequest {
            public function __construct(
                private readonly HttpMiddleware $middleware,
                private readonly CanHandleHttpRequest $next,
            ) {}

            #[\Override]
            public function handle(HttpRequest $request): HttpResponse {
                return $this->middleware->handle($request, $this->next);
            }
        };
    }
}
