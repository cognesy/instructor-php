<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
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
     * @param HttpMiddleware[] $middleware The list of middlewares
     * @param EventDispatcherInterface|null $events Event dispatcher for middleware events
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
    #[\Override]
    public function handle(HttpRequest $request): HttpResponse {
        // We'll build a chain in reverse so the first middleware is called first.
        $chainedHandler = array_reduce(
            array_reverse($this->middleware),
            function (CanHandleHttpRequest $next, HttpMiddleware $middleware) {
                return $this->makeInstance($middleware, $next);
            },
            $this->driver,
        );

        // Now $chainedHandler is a single object implementing CanHandleHttp
        // that internally calls all middlewares, then final driver.
        return $chainedHandler->handle($request);
    }

    /**
     * Create handler instance with next middleware in the chain
     */
    private function makeInstance(HttpMiddleware $middleware, CanHandleHttpRequest $next): CanHandleHttpRequest {
        return new class($middleware, $next) implements CanHandleHttpRequest {
            public function __construct(
                private HttpMiddleware $middleware,
                private CanHandleHttpRequest $next,
            ) {}

            #[\Override]
            public function handle(HttpRequest $request): HttpResponse {
                return $this->middleware->handle($request, $this->next);
            }
        };
    }
}