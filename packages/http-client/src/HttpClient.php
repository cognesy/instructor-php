<?php
namespace Cognesy\Http;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;

/**
 * HTTP client adapter that provides unified access to underlying
 * HTTP client implementation and middleware stack.
 */
class HttpClient
{
    use HandlesEvents;

    private readonly CanHandleHttpRequest $driver;
    private readonly MiddlewareStack $middlewareStack;

    public function __construct(
        CanHandleHttpRequest $driver,
        MiddlewareStack $middlewareStack,
        CanHandleEvents $events,
    ) {
        $this->driver = $driver;
        $this->middlewareStack = $middlewareStack;
        $this->events = EventBusResolver::using($events);
    }

    /**
     * Handles the HTTP request using the configured driver and middleware stack.
     */
    public function withRequest(HttpRequest $request): PendingHttpResponse {
        return new PendingHttpResponse(
            request: $request,
            handler: $this->middlewareStack->decorate($this->driver),
        );
    }

    /**
     * Returns the middleware stack (read-only access).
     */
    public function middleware(): MiddlewareStack {
        return $this->middlewareStack;
    }

    public function toDebugArray(): array {
        return [
            'driver' => $this->driver::class,
            'middleware' => $this->middlewareStack->toDebugArray(),
        ];
    }
}