<?php
namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanProvideDebugConfig;
use Cognesy\Http\Contracts\CanProvideHttpClientConfig;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Debug\Debug;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Http\Middleware\BufferResponse\BufferResponseMiddleware;
use Cognesy\Http\Middleware\Debug\DebugMiddleware;
use Cognesy\Utils\Deferred;
use Cognesy\Utils\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Utils\Events\EventHandlerFactory;
use Cognesy\Utils\Events\Traits\HandlesEventDispatching;
use Cognesy\Utils\Events\Traits\HandlesEventListening;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The HttpClient class is a wrapper around an HTTP client driver (class implementing
 * CanHandleHttpRequest).
 *
 * It enriches any underlying HTTP client or mechanism with:
 *  - a middleware stack for processing HTTP requests
 *  - unified support for extra capabilities like debugging or buffering
 */
class HttpClient
{
    use HandlesEventDispatching;
    use HandlesEventListening;

    protected ?bool $debug = null;

    protected Deferred $debugConfig;
    protected Deferred $stack;
    protected Deferred $config;
    protected Deferred $driver;

    protected CanProvideHttpClientConfig $httpConfigProvider;
    protected CanProvideDebugConfig $debugConfigProvider;

    /**
     * Constructor method for initializing the HTTP client.
     */
    public function __construct(
        ?EventDispatcherInterface  $events = null,
        ?CanRegisterEventListeners $listener = null,
        ?CanProvideHttpClientConfig $httpConfigProvider = null,
        ?CanProvideDebugConfig $debugConfigProvider = null,
    ) {
        $eventHandlerFactory = new EventHandlerFactory($events, $listener);
        $this->events = $eventHandlerFactory->dispatcher();
        $this->listener = $eventHandlerFactory->listener();
        $this->httpConfigProvider = $httpConfigProvider ?? new SettingsHttpClientConfigProvider();
        $this->debugConfigProvider = $debugConfigProvider ?? new SettingsDebugConfigProvider();

        $this->stack = $this->deferMiddlewareStackCreation();
        $this->driver = $this->deferHttpClientDriverCreation();
        $this->debugConfig = $this->deferDebugConfigCreation();
        $this->config = $this->deferHttpClientConfigCreation();
    }

    public function using(string $preset): self {
        return $this->withPreset($preset);
    }

    public function withPreset(string $preset): self {
        $this->config = $this->deferHttpClientConfigCreation(preset: $preset);
        return $this;
    }

    public function withConfig(HttpClientConfig $config): self {
        $this->config = $this->deferHttpClientConfigCreation(config: $config);
        return $this;
    }

    public function withDriver(CanHandleHttpRequest $driver): self {
        $this->driver = $this->deferHttpClientDriverCreation($driver);
        return $this;
    }

    public function withClientInstance(object $clientInstance): self {
        $this->driver = $this->deferHttpClientDriverCreation(clientInstance: $clientInstance);
        return $this;
    }

    /**
     * Returns the middleware stack associated with the current HTTP client.
     * Allows to manipulate the middleware stack directly.
     */
    public function middleware(): MiddlewareStack {
        return $this->makeStack();
    }

    /**
     * Adds middleware to the stack.
     *
     * @param HttpMiddleware ...$middleware The middleware to add to the stack.
     * @return self Returns the instance of the class for method chaining.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    public function withMiddleware(HttpMiddleware ...$middleware): self {
        $this->makeStack()->appendMany($middleware);
        return $this;
    }

    public function withDebug(?bool $debug = true) : self {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Handles the HTTP request using the current HTTP driver
     * via a stack of middleware to process the request and response.
     *
     * @param HttpClientRequest $request The request to be processed.
     * @return HttpClientResponse The response indicating the access result after processing the request.
     */
    public function handle(HttpClientRequest $request): HttpClientResponse {
        return $this
            ->makeStack()
            ->decorate($this->makeHttpClientDriver())
            ->handle($request);
    }

    // INTERNAL //////////////////////////////////////////////////////

    private function deferHttpClientDriverCreation(
        ?CanHandleHttpRequest $driver = null,
        ?object $clientInstance = null,
    ): Deferred {
        return new Deferred(
            function() use ($driver, $clientInstance) {
                if ($driver !== null) {
                    return $driver;
                }
                return (new HttpClientDriverFactory($this->events))->makeDriver(
                    config: $this->makeHttpClientConfig(),
                    clientInstance: $clientInstance,
                );
            }
        );
    }

    private function deferHttpClientConfigCreation(
        ?string $preset = null,
        ?HttpClientConfig $config = null,
    ): Deferred {
        return new Deferred(
            fn() => $config ?? $this->httpConfigProvider->getConfig($preset)
        );
    }

    private function deferDebugConfigCreation(
        ?DebugConfig $debugConfig = null,
    ): Deferred {
        return new Deferred(
            function(?bool $debug) use ($debugConfig) {
                $result = $debugConfig ?? $this->debugConfigProvider->getConfig();
                if ($debug !== null) {
                    $result->httpEnabled = $debug;
                }
                return $result;
            }
        );
    }

    private function deferMiddlewareStackCreation(
        ?MiddlewareStack $stack = null,
    ): Deferred {
        return new Deferred(
            function(?bool $debug) use ($stack) {
                return $this->applyDebug(
                    $stack ?? new MiddlewareStack($this->events),
                    $this->makeDebugConfig(),
                    $debug,
                );
            }
        );
    }

    private function makeHttpClientDriver() : CanHandleHttpRequest {
        return $this->driver->resolve();
    }

    private function makeStack() : MiddlewareStack {
        return $this->stack->resolveUsing($this->debug);
    }

    private function makeHttpClientConfig() : HttpClientConfig {
        return $this->config->resolve();
    }

    private function makeDebugConfig() : DebugConfig {
        return $this->debugConfig->resolveUsing($this->debug);
    }

    private function applyDebug(
        MiddlewareStack $stack,
        DebugConfig $debugConfig,
        ?bool $debug,
    ) : MiddlewareStack {
        if ($debug === null && !$debugConfig->httpEnabled) {
            return $stack;
        }

        if ($debug === null && $debugConfig->httpEnabled) {
            $debug = true;
        }

        if ($debug) {
            // force http enabled
            $debugConfig->httpEnabled = true;
            $stack->prepend(new BufferResponseMiddleware(), 'internal:buffering');
            $stack->prepend(new DebugMiddleware(new Debug($debugConfig), $this->events), 'internal:debug');
        } else {
            // remove debug middleware
            $debugConfig->httpEnabled = false;
            $stack->remove('internal:debug');
            $stack->remove('internal:buffering');
        }
        return $stack;
    }
}
