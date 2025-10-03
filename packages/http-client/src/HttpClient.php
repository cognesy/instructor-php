<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Config\HttpClientConfig;
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

    /**
     * Handles a pool of HTTP requests concurrently using the configured driver.
     *
     * @param HttpRequest[] $requests Array of HttpRequest objects to be processed
     * @param int|null $maxConcurrent Maximum number of concurrent requests
     * @return array Array of Result objects containing HttpResponse or exceptions
     */
    public function pool(array $requests, ?int $maxConcurrent = null): array {
        $poolHandler = $this->driverFactory->makePoolHandler($this->getConfigFromDriver());
        return $poolHandler->pool($requests, $maxConcurrent);
    }

    /**
     * Creates a pending pool that can be executed later with deferred execution.
     *
     * @param HttpRequest[] $requests Array of HttpRequest objects to be processed
     * @return PendingHttpPool Deferred pool execution object
     */
    public function withPool(array $requests): PendingHttpPool {
        $poolHandler = $this->driverFactory->makePoolHandler($this->getConfigFromDriver());
        return new PendingHttpPool($requests, $poolHandler);
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function makeDefaultDriver(): CanHandleHttpRequest {
        return $this->driverFactory->makeDriver();
    }

    /**
     * Extracts configuration from the current driver to pass to pool handler.
     */
    private function getConfigFromDriver(): null {
        // For now, we'll let the factory create with default config
        // In future, we might want to extract actual config from driver
        return null;
    }
}