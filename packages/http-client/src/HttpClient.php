<?php
namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\Traits\HandlesEventDispatching;
use Cognesy\Utils\Events\Traits\HandlesEventListening;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * HTTP client adapter that provides unified access to underlying
 * HTTP client implementation and middleware stack.
 */
class HttpClient
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    private readonly CanHandleHttpRequest $driver;
    private readonly MiddlewareStack $middlewareStack;

    public function __construct(
        CanHandleHttpRequest $driver,
        MiddlewareStack $middlewareStack,
        EventDispatcherInterface $events,
        ?CanRegisterEventListeners $listener = null
    ) {
        $this->driver = $driver;
        $this->middlewareStack = $middlewareStack;
        $this->events = $events;
        $this->listener = $listener;
    }

    /**
     * Handles the HTTP request using the configured driver and middleware stack.
     */
    public function handle(HttpClientRequest $request): HttpClientResponse
    {
        return $this->middlewareStack
            ->decorate($this->driver)
            ->handle($request);
    }

    /**
     * Returns the middleware stack (read-only access).
     */
    public function middleware(): MiddlewareStack
    {
        return $this->middlewareStack;
    }
}