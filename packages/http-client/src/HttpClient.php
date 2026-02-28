<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Creation\HttpClientDriverFactory;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Drivers\ExtHttp\ExtHttpDriver;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Laravel\LaravelDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;
use Cognesy\Http\Middleware\MiddlewareStack;
use InvalidArgumentException;
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
    private readonly HttpClientConfig $config;
    private readonly ?CanHandleRequestPool $poolHandler;

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
        ?HttpClientConfig $config = null,
        ?CanHandleRequestPool $poolHandler = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->driverFactory = new HttpClientDriverFactory($this->events);
        $this->config = $this->resolveConfig($driver, $config);
        $this->driver = $driver ?? $this->makeDefaultDriver($this->config);
        $this->poolHandler = $poolHandler;
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
            poolHandler: $this->poolHandler,
        );
    }

    public function withMiddleware(HttpMiddleware $middleware, ?string $name = null): self {
        $newStack = $this->middlewareStack->append($middleware, $name);
        return new self(
            driver: $this->driver,
            middlewareStack: $newStack,
            events: $this->events,
            config: $this->config,
            poolHandler: $this->poolHandler,
        );
    }

    public function withoutMiddleware(string $name): self {
        $newStack = $this->middlewareStack->remove($name);
        return new self(
            driver: $this->driver,
            middlewareStack: $newStack,
            events: $this->events,
            config: $this->config,
            poolHandler: $this->poolHandler,
        );
    }

    public function withPoolHandler(CanHandleRequestPool $poolHandler): self {
        return new self(
            driver: $this->driver,
            middlewareStack: $this->middlewareStack,
            events: $this->events,
            config: $this->config,
            poolHandler: $poolHandler,
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
     * @param HttpRequestList $requests Collection of HttpRequest objects to be processed
     * @param int|null $maxConcurrent Maximum number of concurrent requests
     * @return HttpResponseList Collection of Result objects containing HttpResponse or exceptions
     */
    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
        $poolHandler = $this->resolvePoolHandler();
        return $poolHandler->pool($requests, $maxConcurrent);
    }

    /**
     * Creates a pending pool that can be executed later with deferred execution.
     *
     * @param HttpRequestList $requests Collection of HttpRequest objects to be processed
     * @return PendingHttpPool Deferred pool execution object
     */
    public function withPool(HttpRequestList $requests): PendingHttpPool {
        $poolHandler = $this->resolvePoolHandler();
        return new PendingHttpPool($requests, $poolHandler);
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
            poolHandler: $this->poolHandler,
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

    private function resolvePoolDriverName(): string {
        $driverName = $this->resolveDriverName($this->driver);
        return match (true) {
            $driverName !== null => $driverName,
            default => throw new InvalidArgumentException(sprintf(
                'Driver "%s" does not support request pooling via HttpClient::pool(). Use a built-in driver preset for pooling.',
                get_debug_type($this->driver),
            )),
        };
    }

    private function resolvePoolHandler(): CanHandleRequestPool {
        $configDriverName = $this->config->driver;
        return match (true) {
            $this->poolHandler !== null => $this->poolHandler,
            $this->driverFactory->hasRegisteredPoolHandler($configDriverName) => $this->driverFactory->makePoolHandler(
                config: $this->config,
                driver: $configDriverName,
            ),
            default => $this->driverFactory->makePoolHandler(
                config: $this->config,
                driver: $this->resolvePoolDriverName(),
            ),
        };
    }

    private function resolveDriverName(CanHandleHttpRequest $driver): ?string {
        return match (true) {
            $driver instanceof CurlDriver => 'curl',
            $driver instanceof ExtHttpDriver => 'exthttp',
            $driver instanceof GuzzleDriver => 'guzzle',
            $driver instanceof SymfonyDriver => 'symfony',
            $driver instanceof LaravelDriver => 'laravel',
            default => null,
        };
    }
}
