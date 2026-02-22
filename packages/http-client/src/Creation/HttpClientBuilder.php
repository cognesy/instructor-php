<?php declare(strict_types=1);

namespace Cognesy\Http\Creation;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Events\ConfigResolutionFailed;
use Cognesy\Config\Events\ConfigResolved;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
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
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Fluent builder for creating HttpClient instances in a type-safe way.
 */
final class HttpClientBuilder
{
    private ConfigPresets $presets;
    private CanHandleEvents $events;

    private ?string $preset = null;
    private ?string $debugPreset = null;
    private ?HttpClientConfig $config = null;
    private ?DebugConfig $debugConfig = null;
    private ?CanHandleHttpRequest $driver = null;
    private ?string $driverName = null;
    private ?object $clientInstance = null;
    /** @var HttpMiddleware[] */
    private array $middleware = [];

    public function __construct(
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->presets = ConfigPresets::using($configProvider);
    }

    public function using(string $preset): self {
        return $this->withPreset($preset);
    }

    public function withPreset(string $preset): self {
        $this->preset = $preset;
        return $this;
    }

    public function withDebugPreset(?string $preset): self {
        return $this->withHttpDebugPreset($preset);
    }

    public function withHttpDebugPreset(?string $preset): self {
        $this->debugPreset = $preset;
        return $this;
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

    public function withConfigProvider(CanProvideConfig $configProvider): self {
        $this->presets = $this->presets->withConfigProvider($configProvider);
        return $this;
    }

    public function withDriver(CanHandleHttpRequest $driver): self {
        $this->driver = $driver;
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

    public function withCircuitBreakerPolicy(CircuitBreakerPolicy $policy): self {
        return $this->withMiddleware(new CircuitBreakerMiddleware($policy));
    }

    public function withIdempotencyMiddleware(IdempotencyMiddleware $middleware): self {
        return $this->withMiddleware($middleware);
    }

    public function withEventBus(CanHandleEvents|EventDispatcherInterface $events): self {
        $this->events = EventBusResolver::using($events);
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
        );
    }

    private function buildHttpClientConfig(): HttpClientConfig {
        if ($this->config !== null) {
            $config = match(true) {
                ($this->driverName !== null) => $this->config->withOverrides(['driver' => $this->driverName]),
                default => $this->config,
            };
            $this->events->dispatch(new ConfigResolved([
                'group' => HttpClientConfig::group(),
                'config' => $config,
            ]));
            return $config;
        }

        $result = Result::try(fn() => $this->presets
            ->for(HttpClientConfig::group())
            ->getOrDefault($this->preset));
        if ($result->isFailure()) {
            $this->events->dispatch(new ConfigResolutionFailed([
                'group' => 'http',
                'preset' => $this->preset,
                'exception' => $result->exception(),
            ]));
            throw $result->exception();
        }

        $data = $result->unwrap();
        $data['driver'] = match(true) {
            ($this->driverName !== null) => $this->driverName,
            default => $data['driver'] ?? '',
        };

        $this->events->dispatch(new ConfigResolved([
            'group' => 'http',
            'config' => $data,
            'preset' => $this->preset,
        ]));

        return HttpClientConfig::fromArray($data);
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
        $result = Result::try(fn() => match(true) {
            $this->debugConfig !== null => $this->debugConfig,
            default => $this->presets
                ->for(DebugConfig::group())
                ->getOrDefault($this->debugPreset),
        });

        if ($result->isFailure()) {
            $this->events->dispatch(new ConfigResolutionFailed([
                'group' => 'debug',
                'preset' => $this->debugPreset,
                'exception' => $result->exception(),
            ]));
            throw $result->exception();
        }

        $config = $result->unwrap();

        $this->events->dispatch(new ConfigResolved([
            'group' => 'debug',
            'config' => $config,
            'preset' => $this->debugPreset,
        ]));

        if (is_array($config)) {
            return DebugConfig::fromArray($config);
        }
        if ($config instanceof DebugConfig) {
            return $config;
        }
        throw new \InvalidArgumentException('Invalid debug configuration type');
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
        $stack->prepend($eventSource, 'internal:eventsource');
        return $stack;
    }

    private function makeDefaultStack() : MiddlewareStack {
        return new MiddlewareStack($this->events);
    }
}
