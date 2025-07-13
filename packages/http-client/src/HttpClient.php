<?php
namespace Cognesy\Http;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * HTTP client adapter that provides unified access to underlying
 * HTTP client implementation and middleware stack.
 */
class HttpClient
{
    use HandlesEvents;

    private readonly CanHandleHttpRequest $driver;
    private readonly MiddlewareStack $middlewareStack;
    private readonly HttpClientDriverFactory $driverFactory;

    public static function using(string $preset) : self {
        return (new HttpClientBuilder())->withPreset($preset)->create();
    }

    public static function default() : self {
        return (new HttpClientBuilder())->create();
    }

    public function __construct(
        ?CanHandleHttpRequest $driver = null,
        ?MiddlewareStack $middlewareStack = null,
        null|EventDispatcherInterface|CanHandleEvents $events = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->driverFactory = new HttpClientDriverFactory($this->events);
        $this->driver = $driver ?? $this->makeDefaultDriver();
        $this->middlewareStack = $middlewareStack ?? new MiddlewareStack(
            events: $this->events,
            middlewares: [],
        );
    }

    public function withMiddlewareStack(MiddlewareStack $middlewareStack): self {
        return new self(
            driver: $this->driver,
            middlewareStack: $middlewareStack,
            events: $this->events,
        );
    }

    public function withMiddleware(HttpMiddleware $middleware, ?string $name = null): self {
        $newStack = $this->middlewareStack->append($middleware, $name);
        return new self(
            driver: $this->driver,
            middlewareStack: $newStack,
            events: $this->events,
        );
    }

    public function withoutMiddleware(string $name): self {
        $newStack = $this->middlewareStack->remove($name);
        return new self(
            driver: $this->driver,
            middlewareStack: $newStack,
            events: $this->events,
        );
    }

    /**
     * Handles the HTTP request using the configured driver and middleware stack.
     */
    public function withRequest(HttpRequest $request): PendingHttpResponse {
        return new PendingHttpResponse(
            request: $request,
            driver: $this->middlewareStack->decorate($this->driver),
        );
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function makeDefaultDriver(): CanHandleHttpRequest {
        return $this->driverFactory->makeDriver();
    }
}