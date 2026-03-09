<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Creation\HttpClientDriverFactory;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;
use Cognesy\Http\Middleware\MiddlewareStack;

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
    private readonly HttpClientConfig $config;

    public static function default() : self {
        return (new HttpClientBuilder())->create();
    }

    public static function fromConfig(HttpClientConfig $config) : self {
        return (new HttpClientBuilder())
            ->withConfig($config)
            ->create();
    }

    public function __construct(
        ?CanHandleHttpRequest $driver = null,
        ?MiddlewareStack $middlewareStack = null,
        ?CanHandleEvents $events = null,
        ?HttpClientConfig $config = null,
    ) {
        $this->events = $this->resolveEvents($events);
        $this->driverFactory = new HttpClientDriverFactory($this->events);
        $this->config = $this->resolveConfig($driver, $config);
        $this->driver = $driver ?? $this->makeDefaultDriver($this->config);
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
            config: $this->config,
        );
    }

    public function withMiddleware(HttpMiddleware $middleware, ?string $name = null): self {
        $newStack = $this->middlewareStack->append($middleware, $name);
        return new self(
            driver: $this->driver,
            middlewareStack: $newStack,
            events: $this->events,
            config: $this->config,
        );
    }

    public function withoutMiddleware(string $name): self {
        $newStack = $this->middlewareStack->remove($name);
        return new self(
            driver: $this->driver,
            middlewareStack: $newStack,
            events: $this->events,
            config: $this->config,
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

    /**
     * @deprecated Use withMiddleware((new EventSourceMiddleware())->withParser(...)) for explicit SSE behavior.
     */
    public function withSSEStream() : self {
        $sse = (new EventSourceMiddleware(true))
            ->withParser(static fn(string $payload) => $payload);

        return new self(
            driver: $this->driver,
            middlewareStack: $this->middlewareStack->append(
                $sse
            ),
            events: $this->events,
            config: $this->config,
        );
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function makeDefaultDriver(HttpClientConfig $config): CanHandleHttpRequest {
        return $this->driverFactory->makeDriver(config: $config);
    }

    private function resolveConfig(?CanHandleHttpRequest $driver, ?HttpClientConfig $config): HttpClientConfig {
        if ($config !== null) {
            return $config;
        }

        if ($driver === null) {
            return new HttpClientConfig(driver: 'curl');
        }

        $driverName = $this->resolveDriverName($driver);
        return match (true) {
            $driverName !== null => new HttpClientConfig(driver: $driverName),
            default => new HttpClientConfig(),
        };
    }

    private function resolveEvents(?CanHandleEvents $events): CanHandleEvents {
        if ($events !== null) {
            return $events;
        }
        return new EventDispatcher(name: 'http.client');
    }
}
