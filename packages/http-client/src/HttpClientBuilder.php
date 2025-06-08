<?php
namespace Cognesy\Http;

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Events\HttpClientBuilt;
use Cognesy\Http\Middleware\BufferResponse\BufferResponseMiddleware;
use Cognesy\Http\Middleware\Debug\ConsoleDebug;
use Cognesy\Http\Middleware\Debug\Debug;
use Cognesy\Http\Middleware\Debug\DebugMiddleware;
use Cognesy\Http\Middleware\Debug\EventsDebug;
use Cognesy\Utils\Config\Contracts\CanProvideConfig;
use Cognesy\Utils\Config\Events\ConfigResolutionFailed;
use Cognesy\Utils\Config\Events\ConfigResolved;
use Cognesy\Utils\Config\Providers\ConfigResolver;
use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\EventHandlerFactory;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Fluent builder for creating HttpClient instances in a type-safe way.
 */
final class HttpClientBuilder
{
    private ?string $preset = null;
    private ?string $debugPreset = null;
    private ?HttpClientConfig $config = null;
    private ?DebugConfig $debugConfig = null;
    private ?CanHandleHttpRequest $driver = null;
    private ?object $clientInstance = null;
    private array $middleware = [];

    private CanProvideConfig $configProvider;
    private EventDispatcherInterface $events;
    private CanRegisterEventListeners $listener;

    public function __construct(
        ?EventDispatcherInterface  $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideConfig          $configProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();
        $this->configProvider = ConfigResolver::makeWith($configProvider);
    }

    public function using(string $preset): self {
        return $this->withPreset($preset);
    }

    public function withPreset(string $preset): self {
        $this->preset = $preset;
        return $this;
    }

    public function withDebugPreset(?string $preset): self {
        $this->debugPreset = $preset;
        return $this;
    }

    public function withConfig(HttpClientConfig $config): self {
        $this->config = $config;
        return $this;
    }

    public function withDebugConfig(DebugConfig $debugConfig): self {
        $this->debugConfig = $debugConfig;
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider): self {
        $this->configProvider = $configProvider;
        return $this;
    }

    public function withDriver(CanHandleHttpRequest $driver): self {
        $this->driver = $driver;
        return $this;
    }

    public function withClientInstance(object $clientInstance): self {
        $this->clientInstance = $clientInstance;
        return $this;
    }

    public function withMiddleware(HttpMiddleware ...$middleware): self {
        $this->middleware = [...$this->middleware, ...$middleware];
        return $this;
    }

    public function withEventDispatcher(EventDispatcherInterface $events): self {
        $this->events = $events;
        return $this;
    }

    public function withEventListener(CanRegisterEventListeners $listener): self {
        $this->listener = $listener;
        return $this;
    }

    public function create(): HttpClient {
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
            listener: $this->listener
        );
    }

    private function buildHttpClientConfig(): HttpClientConfig {
        if ($this->config !== null) {
            $this->events->dispatch(new ConfigResolved([
                'group' => HttpClientConfig::group(),
                'config' => $this->config,
            ]));
            return $this->config;
        }

        $result = Result::try(fn() => $this->configProvider->getConfig(HttpClientConfig::group(), $this->preset));
        if ($result->isFailure()) {
            $this->events->dispatch(new ConfigResolutionFailed([
                'group' => 'http',
                'preset' => $this->preset,
                'exception' => $result->exception(),
            ]));
            throw $result->exception();
        }

        $this->events->dispatch(new ConfigResolved([
            'group' => 'http',
            'config' => $result->unwrap(),
            'preset' => $this->preset,
        ]));

        return HttpClientConfig::fromArray($result->unwrap());
    }

    private function buildDebugConfig(): DebugConfig {
        $result = Result::try(fn() => match(true) {
            $this->debugConfig !== null => $this->debugConfig,
            !empty($this->debugPreset) => $this->configProvider->getConfig(DebugConfig::group(), $this->debugPreset),
            default => $this->configProvider->getConfig(DebugConfig::group()),
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

        return DebugConfig::fromArray($config);
    }

    private function buildDriver(HttpClientConfig $config): CanHandleHttpRequest {
        if ($this->driver !== null) {
            return $this->driver;
        }

        return (new HttpClientDriverFactory($this->events))->makeDriver(
            config: $config,
            clientInstance: $this->clientInstance,
        );
    }

    private function buildMiddlewareStack(DebugConfig $debugConfig): MiddlewareStack {
        $stack = new MiddlewareStack($this->events);

        // Add debug middleware if enabled
        if ($debugConfig->httpEnabled) {
            $debugHandler = $this->makeDebugHandler($debugConfig);
            $stack->prepend(new BufferResponseMiddleware(), 'internal:buffering');
            $stack->prepend(new DebugMiddleware($debugHandler), 'internal:debug');
        } else {
            $stack->remove('internal:debug');
            $stack->remove('internal:buffering');
        }

        // Add custom middleware
        $stack->appendMany($this->middleware);

        return $stack;
    }

    private function makeDebugHandler(DebugConfig $debugConfig) : Debug {
        return (new Debug($debugConfig))
            ->withHandlers(
                new ConsoleDebug($debugConfig),
                new EventsDebug($debugConfig, $this->events),
            );
    }
}