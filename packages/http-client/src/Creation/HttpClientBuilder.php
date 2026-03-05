<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Middleware\CanStoreCircuitBreakerState;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Events\HttpClientBuilt;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\CircuitBreakerMiddleware;
use Cognesy\Http\Middleware\CircuitBreakerPolicy;
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;
use Cognesy\Http\Middleware\EventSource\Listeners\DispatchDebugEvents;
use Cognesy\Http\Middleware\EventSource\Listeners\PrintToConsole;
use Cognesy\Http\Middleware\IdempotencyMiddleware;
use Cognesy\Http\Middleware\RetryMiddleware;
use Cognesy\Http\Middleware\RetryPolicy;
use Cognesy\Http\Middleware\MiddlewareStack;

/**
 * Fluent builder for creating HttpClient instances in a type-safe way.
 */
final class HttpClientBuilder
{
    private CanHandleEvents $events;

    private ?HttpClientConfig $config = null;
    private ?DebugConfig $debugConfig = null;
    private ?CanHandleHttpRequest $driver = null;
    private ?CanHandleRequestPool $poolHandler = null;
    private ?string $driverName = null;
    private ?object $clientInstance = null;
    /** @var HttpMiddleware[] */
    private array $middleware = [];

    public function __construct(
        ?CanHandleEvents $events = null,
    ) {
        $this->events = $this->resolveEvents($events);
    }

    public function withConfig(HttpClientConfig $config): self {
        $this->config = $config;
        return $this;
    }

    public function withDsn(string $dsn): self {
        $this->config = HttpClientConfig::fromDsn($dsn);
        return $this;
    }

    public function withDebugConfig(DebugConfig $debugConfig): self {
        $this->debugConfig = $debugConfig;
        return $this;
    }

    public function withDriver(CanHandleHttpRequest $driver): self {
        $this->driver = $driver;
        return $this;
    }

    public function withPoolHandler(CanHandleRequestPool $poolHandler): self {
        $this->poolHandler = $poolHandler;
        return $this;
    }

    /**
     * Convenience: attach a MockHttpDriver and optionally configure expectations.
     * @param callable(MockHttpDriver): void|null $configure
     */
    public function withMock(?callable $configure = null): self {
        $eventDispatcher = $this->events instanceof \Cognesy\Events\Dispatchers\EventDispatcher
            ? $this->events
            : null;
        $mock = new MockHttpDriver($eventDispatcher);
        if ($configure) {
            $configure($mock);
        }
        return $this->withDriver($mock);
    }

    public function withClientInstance(
        string $driverName,
        object $clientInstance,
    ): self {
        $this->driverName = $driverName;
        $this->clientInstance = $clientInstance;
        return $this;
    }

    public function withMiddleware(HttpMiddleware ...$middleware): self {
        $this->middleware = [...$this->middleware, ...$middleware];
        return $this;
    }

    public function withRetryPolicy(RetryPolicy $policy): self {
        return $this->withMiddleware(new RetryMiddleware($policy));
    }

    public function withCircuitBreakerPolicy(
        CircuitBreakerPolicy $policy,
        ?CanStoreCircuitBreakerState $stateStore = null,
    ): self {
        return $this->withMiddleware(new CircuitBreakerMiddleware($policy, $stateStore));
    }

    public function withIdempotencyMiddleware(IdempotencyMiddleware $middleware): self {
        return $this->withMiddleware($middleware);
    }

    public function withEventBus(CanHandleEvents $events): self {
        $this->events = $events;
        return $this;
    }

    public function create(): HttpClient {
        return $this->buildClient();
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function buildClient() : HttpClient {
        $config = $this->buildHttpClientConfig();
        $debugConfig = $this->buildDebugConfig();
        $driver = $this->buildDriver($config);
        $middlewareStack = $this->buildMiddlewareStack($debugConfig);

        $this->events->dispatch(new HttpClientBuilt([
            'driver' => get_class($driver),
            'httpConfig' => $config->toArray(),
            'debugConfig' => $debugConfig->toArray(),
            'middlewareStack' => $middlewareStack->toDebugArray(),
        ]));

        return new HttpClient(
            driver: $driver,
            middlewareStack: $middlewareStack,
            events: $this->events,
            config: $config,
            poolHandler: $this->poolHandler,
        );
    }

    private function buildHttpClientConfig(): HttpClientConfig {
        $config = $this->config ?? new HttpClientConfig();
        return match (true) {
            $this->driverName !== null => $config->withOverrides(['driver' => $this->driverName]),
            default => $config,
        };
    }

    private function buildDriver(HttpClientConfig $config): CanHandleHttpRequest {
        if ($this->driver !== null) {
            return $this->driver;
        }
        return (new HttpClientDriverFactory($this->events))
            ->makeDriver(
                config: $config,
                clientInstance: $this->clientInstance,
            );
    }

    private function buildDebugConfig(): DebugConfig {
        return $this->debugConfig ?? new DebugConfig();
    }

    private function resolveEvents(?CanHandleEvents $events): CanHandleEvents {
        if ($events !== null) {
            return $events;
        }
        return new EventDispatcher(name: 'http.client.builder');
    }

    private function buildMiddlewareStack(DebugConfig $debugConfig): MiddlewareStack {
        return match(true) {
            $debugConfig->httpEnabled => $this->makeDebugStack($debugConfig)->appendMany($this->middleware),
            default => $this->makeDefaultStack()->appendMany($this->middleware),
        };
    }

    private function makeDebugStack(DebugConfig $debugConfig) : MiddlewareStack {
        $stack = new MiddlewareStack($this->events);
        $eventSource = (new EventSourceMiddleware(true))->withListeners(
            new PrintToConsole($debugConfig),
            new DispatchDebugEvents($debugConfig, $this->events),
        );
        $stack = $stack->prepend($eventSource, 'internal:eventsource');
        return $stack;
    }

    private function makeDefaultStack() : MiddlewareStack {
        return new MiddlewareStack($this->events);
    }
}
