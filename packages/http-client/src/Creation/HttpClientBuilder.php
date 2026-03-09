<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanProvideHttpDrivers;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Events\HttpClientBuilt;
use Cognesy\Http\Extras\Middleware\CircuitBreakerMiddleware;
use Cognesy\Http\Extras\Middleware\EventSource\EventSourceMiddleware;
use Cognesy\Http\Extras\Middleware\IdempotencyMiddleware;
use Cognesy\Http\Extras\Middleware\RetryMiddleware;
use Cognesy\Http\Extras\Support\CanStoreCircuitBreakerState;
use Cognesy\Http\Extras\Support\CircuitBreakerPolicy;
use Cognesy\Http\Extras\Support\EventSource\Listeners\DispatchDebugEvents;
use Cognesy\Http\Extras\Support\EventSource\Listeners\PrintToConsole;
use Cognesy\Http\Extras\Support\RetryPolicy;
use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientRuntime;
use Cognesy\Http\Middleware\MiddlewareStack;

final class HttpClientBuilder
{
    private CanHandleEvents $events;
    private ?HttpClientConfig $config = null;
    private ?DebugConfig $debugConfig = null;
    private ?CanHandleHttpRequest $driver = null;
    private ?CanProvideHttpDrivers $drivers = null;
    private ?string $driverName = null;
    private ?object $clientInstance = null;
    /** @var HttpMiddleware[] */
    private array $middleware = [];

    public function __construct(?CanHandleEvents $events = null)
    {
        $this->events = $events ?? new EventDispatcher(name: 'http.client.builder');
    }

    public function withConfig(HttpClientConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function withDsn(string $dsn): self
    {
        $this->config = HttpClientConfig::fromDsn($dsn);
        return $this;
    }

    public function withDebugConfig(DebugConfig $debugConfig): self
    {
        $this->debugConfig = $debugConfig;
        return $this;
    }

    public function withDriver(CanHandleHttpRequest $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    public function withDrivers(CanProvideHttpDrivers $drivers): self
    {
        $this->drivers = $drivers;
        return $this;
    }

    /** @param callable(MockHttpDriver): void|null $configure */
    public function withMock(?callable $configure = null): self
    {
        $dispatcher = $this->events instanceof EventDispatcher ? $this->events : null;
        $mock = new MockHttpDriver($dispatcher);

        if ($configure !== null) {
            $configure($mock);
        }

        return $this->withDriver($mock);
    }

    public function withClientInstance(string $driverName, object $clientInstance): self
    {
        $this->driverName = $driverName;
        $this->clientInstance = $clientInstance;
        return $this;
    }

    public function withMiddleware(HttpMiddleware ...$middleware): self
    {
        $this->middleware = [...$this->middleware, ...$middleware];
        return $this;
    }

    public function withRetryPolicy(RetryPolicy $policy): self
    {
        return $this->withMiddleware(new RetryMiddleware($policy));
    }

    public function withCircuitBreakerPolicy(
        CircuitBreakerPolicy $policy,
        ?CanStoreCircuitBreakerState $stateStore = null,
    ): self {
        return $this->withMiddleware(new CircuitBreakerMiddleware($policy, $stateStore));
    }

    public function withIdempotencyMiddleware(IdempotencyMiddleware $middleware): self
    {
        return $this->withMiddleware($middleware);
    }

    public function withEventBus(CanHandleEvents $events): self
    {
        $this->events = $events;
        return $this;
    }

    public function create(): HttpClient
    {
        return $this->createRuntime()->client();
    }

    public function createRuntime(): HttpClientRuntime
    {
        $config = $this->buildHttpClientConfig();
        $runtime = HttpClientRuntime::fromConfig(
            config: $config,
            events: $this->events,
            driver: $this->driver,
            drivers: $this->drivers,
            clientInstance: $this->clientInstance,
            middlewareStack: $this->buildMiddlewareStack(),
        );

        $this->events->dispatch(new HttpClientBuilt([
            'driver' => get_class($runtime->driver()),
            'httpConfig' => $config->toArray(),
            'debugConfig' => $this->buildDebugConfig()->toArray(),
            'middlewareStack' => $runtime->middlewareStack()->toDebugArray(),
        ]));

        return $runtime;
    }

    private function buildHttpClientConfig(): HttpClientConfig
    {
        $config = $this->config ?? new HttpClientConfig();

        return match (true) {
            $this->driverName !== null => $config->withOverrides(['driver' => $this->driverName]),
            default => $config,
        };
    }

    private function buildDebugConfig(): DebugConfig
    {
        return $this->debugConfig ?? new DebugConfig();
    }

    private function buildMiddlewareStack(): MiddlewareStack
    {
        $debugConfig = $this->buildDebugConfig();

        return match (true) {
            $debugConfig->httpEnabled => $this->makeDebugStack($debugConfig)->appendMany($this->middleware),
            default => new MiddlewareStack($this->events, $this->middleware),
        };
    }

    private function makeDebugStack(DebugConfig $debugConfig): MiddlewareStack
    {
        return (new MiddlewareStack($this->events))->prepend(
            (new EventSourceMiddleware(true))->withListeners(
                new PrintToConsole($debugConfig),
                new DispatchDebugEvents($debugConfig, $this->events),
            ),
            'internal:eventsource',
        );
    }
}
