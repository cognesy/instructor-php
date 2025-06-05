<?php
namespace Cognesy\Http;

use Cognesy\Http\ConfigProviders\DebugConfigSource;
use Cognesy\Http\ConfigProviders\HttpClientConfigSource;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanProvideDebugConfig;
use Cognesy\Http\Contracts\CanProvideHttpClientConfig;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Debug\Debug;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Http\Events\HttpClientBuilt;
use Cognesy\Http\Middleware\BufferResponse\BufferResponseMiddleware;
use Cognesy\Http\Middleware\Debug\DebugMiddleware;
use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\EventHandlerFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Fluent builder for creating HttpClient instances in a type-safe way.
 */
final class HttpClientBuilder
{
    private ?string $preset = null;
    private ?string $debugPreset = null;
    private ?bool $debug = null;
    private ?HttpClientConfig $config = null;
    private ?DebugConfig $debugConfig = null;
    private ?CanHandleHttpRequest $driver = null;
    private ?object $clientInstance = null;
    private array $middleware = [];

    private CanProvideHttpClientConfig $httpConfigProvider;
    private CanProvideDebugConfig $debugConfigProvider;
    private EventDispatcherInterface $events;
    private CanRegisterEventListeners $listener;

    public function __construct(
        ?EventDispatcherInterface $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideHttpClientConfig $httpConfigProvider = null,
        ?CanProvideDebugConfig $debugConfigProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();
        $this->debugConfigProvider = DebugConfigSource::makeWith($debugConfigProvider);
        $this->httpConfigProvider = HttpClientConfigSource::makeWith($httpConfigProvider);
    }

    /**
     * Set configuration preset.
     */
    public function using(string $preset): self
    {
        return $this->withPreset($preset);
    }

    /**
     * Set configuration preset.
     */
    public function withPreset(string $preset): self
    {
        $this->preset = $preset;
        return $this;
    }

    public function withDebugPreset(string $preset): self
    {
        $this->debugPreset = $preset;
        return $this;
    }

    /**
     * Set explicit HTTP client configuration.
     */
    public function withConfig(HttpClientConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Set HTTP configuration provider.
     */
    public function withHttpConfigProvider(CanProvideHttpClientConfig $httpConfigProvider): self
    {
        $this->httpConfigProvider = $httpConfigProvider;
        return $this;
    }

    /**
     * Set explicit debug configuration.
     */
    public function withDebugConfig(DebugConfig $debugConfig): self
    {
        $this->debugConfig = $debugConfig;
        return $this;
    }

    /**
     * Set debug configuration provider.
     */
    public function withDebugConfigProvider(CanProvideDebugConfig $debugConfigProvider): self
    {
        $this->debugConfigProvider = $debugConfigProvider;
        return $this;
    }

    /**
     * Set explicit HTTP driver.
     */
    public function withDriver(CanHandleHttpRequest $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Set explicit client instance.
     */
    public function withClientInstance(object $clientInstance): self
    {
        $this->clientInstance = $clientInstance;
        return $this;
    }

    /**
     * Enable or disable debug mode.
     */
    public function withDebug(?bool $debug = true): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Add middleware to the stack.
     */
    public function withMiddleware(HttpMiddleware ...$middleware): self
    {
        $this->middleware = [...$this->middleware, ...$middleware];
        return $this;
    }

    public function withEventDispatcher(EventDispatcherInterface $events): self
    {
        $this->events = $events;
        return $this;
    }

    public function withEventListener(CanRegisterEventListeners $listener): self
    {
        $this->listener = $listener;
        return $this;
    }

    /**
     * Build the HttpClient instance.
     */
    public function create(): HttpClient
    {
        $config = $this->buildHttpClientConfig();
        $debugConfig = $this->buildDebugConfig();
        $driver = $this->buildDriver($config);
        $middlewareStack = $this->buildMiddlewareStack($debugConfig);

        $this->events->dispatch(new HttpClientBuilt(
            get_class($driver),
            $config,
            $debugConfig,
            $middlewareStack->toDebugArray(),
        ));

        return new HttpClient(
            driver: $driver,
            middlewareStack: $middlewareStack,
            events: $this->events,
            listener: $this->listener
        );
    }

    private function buildHttpClientConfig(): HttpClientConfig
    {
        if ($this->config !== null) {
            return $this->config;
        }

        return $this->httpConfigProvider->getConfig($this->preset);
    }

    private function buildDebugConfig(): DebugConfig
    {
        if ($this->debugConfig !== null) {
            $config = $this->debugConfig;
        } elseif ($this->debugPreset !== null) {
            $config = $this->debugConfigProvider->getConfig($this->debugPreset);
        } else {
            $config = $this->debugConfigProvider->getConfig();
        }

        // Apply explicit debug setting if provided
        if ($this->debug !== null && $config !== null) {
            $config = clone $config;
            $config->httpEnabled = $this->debug;
        }

        return $config;
    }

    private function buildDriver(HttpClientConfig $config): CanHandleHttpRequest
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        return (new HttpClientDriverFactory($this->events))->makeDriver(
            config: $config,
            clientInstance: $this->clientInstance,
        );
    }

    private function buildMiddlewareStack(DebugConfig $debugConfig): MiddlewareStack
    {
        $stack = new MiddlewareStack($this->events);

        // Add debug middleware if enabled
        if ($this->shouldEnableDebug($debugConfig)) {
            $stack->prepend(new BufferResponseMiddleware(), 'internal:buffering');
            $stack->prepend(new DebugMiddleware(new Debug($debugConfig), $this->events), 'internal:debug');
        }

        // Add custom middleware
        $stack->appendMany($this->middleware);

        return $stack;
    }

    private function shouldEnableDebug(DebugConfig $debugConfig): bool
    {
        // Explicit debug setting takes precedence
        if ($this->debug !== null) {
            return $this->debug;
        }

        // Fall back to config setting
        return $debugConfig->httpEnabled;
    }
}