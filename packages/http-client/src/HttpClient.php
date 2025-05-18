<?php
namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Debug\Debug;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Http\Middleware\BufferResponse\BufferResponseMiddleware;
use Cognesy\Http\Middleware\Debug\DebugMiddleware;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

/**
 * The HttpClient class is responsible for managing HTTP client configurations and instantiating
 * appropriate HTTP driver implementations based on the provided configuration.
 *
 * @property EventDispatcher $events  Instance for dispatching events.
 * @property CanHandleHttpRequest $driver    Instance that handles HTTP requests.
 */
class HttpClient implements CanHandleHttpRequest
{
    protected EventDispatcher $events;
    protected CanHandleHttpRequest $driver;
    protected MiddlewareStack $stack;
    protected HttpClientDriverFactory $driverFactory;

    /**
     * Constructor method for initializing the HTTP client.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return void
     */
    public function __construct(string $client = '', ?EventDispatcher $events = null) {
        $this->events = $events ?? new EventDispatcher();
        $this->driverFactory = new HttpClientDriverFactory($this->events);
        $this->stack = new MiddlewareStack($this->events);
        $config = HttpClientConfig::load($client ?: Settings::get('http', "defaultClient"));
        $debugConfig = DebugConfig::load();
        if ($debugConfig->httpEnabled) {
            $this->withDebug($debugConfig->httpEnabled);
        }
        $this->driver = $this->driverFactory->makeDriver($config);
    }

    /**
     * Static factory method to create an instance of the HTTP handler.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return CanHandleHttpRequest Returns an instance that can handle HTTP operations.
     */
    public static function make(string $client = '', ?EventDispatcher $events = null): CanHandleHttpRequest {
        return (new self($client, $events));
    }

    /**
     * Configures the HttpClient instance with the given client name.
     *
     * @param string $name The name of the client to load the configuration for.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withClient(string $name): self {
        $config = HttpClientConfig::load($name);
        $this->driver = $this->driverFactory->makeDriver($config);
        return $this;
    }

    /**
     * Configures the HttpClient instance with the given configuration.
     *
     * @param HttpClientConfig $config The configuration object to set up the HttpClient.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withConfig(HttpClientConfig $config): self {
        $this->driver = $this->driverFactory->makeDriver($config);
        return $this;
    }

    /**
     * Sets the HTTP handler driver for the instance.
     *
     * @param CanHandleHttpRequest $driver The driver capable of handling HTTP requests.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withDriver(CanHandleHttpRequest $driver): self {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Returns the middleware stack associated with the current HTTP client.
     */
    public function middleware(): MiddlewareStack {
        return $this->stack;
    }

    /**
     * Adds middleware to the stack.
     *
     * @param HttpMiddleware ...$middleware The middleware to add to the stack.
     * @return self Returns the instance of the class for method chaining.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    public function withMiddleware(HttpMiddleware ...$middleware): self {
        $this->stack->appendMany($middleware);
        return $this;
    }

    public function withDebug(bool $debug = true) : self {
        if ($debug) {
            // load debug config
            $config = DebugConfig::load();
            // force http enabled
            $config->httpEnabled = true;
            $this->stack->prepend(new BufferResponseMiddleware(), 'internal:buffering');
            $this->stack->prepend(new DebugMiddleware(new Debug($config), $this->events), 'internal:debug');
        } else {
            $this->stack->remove('internal:debug');
            $this->stack->remove('internal:buffering');
        }
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
        return $this->stack->decorate($this->driver)->handle($request);
    }
}
