<?php
namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Debug\Debug;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Http\Drivers\GuzzleDriver;
use Cognesy\Http\Drivers\LaravelDriver;
use Cognesy\Http\Drivers\SymfonyDriver;
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
    protected static array $drivers = [];

    /**
     * Constructor method for initializing the HTTP client.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return void
     */
    public function __construct(string $client = '', ?EventDispatcher $events = null) {
        $this->events = $events ?? new EventDispatcher();
        $this->stack = new MiddlewareStack($this->events);
        $config = HttpClientConfig::load($client ?: Settings::get('http', "defaultClient"));
        $this->driver = $this->makeDriver($config);
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
     * Registers a custom HTTP driver with the specified name and closure.
     *
     * @param string $name The name of the driver to register.
     * @param class-string|callable $driver The closure that creates the driver instance, accepting following closure arguments:
     *   - \Cognesy\Http\Data\HttpClientConfig $config: The configuration object for the HTTP client.
     *   - \Cognesy\Utils\Events\EventDispatcher $events: The event dispatcher instance.
     * @return void
     */
    public static function registerDriver(string $name, string|callable $driver): void {
        self::$drivers[$name] = match(true) {
            is_string($driver) => fn($config, $events) => new $driver($config, $events),
            is_callable($driver) => $driver,
            default => throw new InvalidArgumentException("Invalid driver provided for {$name} - must be a class name or callable."),
        };
    }

    /**
     * Configures the HttpClient instance with the given client name.
     *
     * @param string $name The name of the client to load the configuration for.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withClient(string $name): self {
        $config = HttpClientConfig::load($name);
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    /**
     * Configures the HttpClient instance with the given configuration.
     *
     * @param \Cognesy\Http\Data\HttpClientConfig $config The configuration object to set up the HttpClient.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withConfig(HttpClientConfig $config): self {
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    /**
     * Sets the HTTP handler driver for the instance.
     *
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $driver The driver capable of handling HTTP requests.
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
            $this->stack->prepend(new BufferResponseMiddleware(), 'internal:buffering');
            $this->stack->prepend(new DebugMiddleware(
                new Debug(new DebugConfig(httpEnabled: true)), $this->events),
                'internal:debug'
            );
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
     * @return \Cognesy\Http\Contracts\HttpClientResponse The response indicating the access result after processing the request.
     */
    public function handle(HttpClientRequest $request): HttpClientResponse {
        return $this->stack->decorate($this->driver)->handle($request);
    }

    // INTERNAL ///////////////////////////////////////////////////////

    /**
     * Creates an HTTP driver instance based on the specified configuration.
     *
     * @param \Cognesy\Http\Data\HttpClientConfig $config The configuration object defining the type of HTTP client and its settings.
     * @return \Cognesy\Http\Contracts\CanHandleHttpRequest The instantiated HTTP driver corresponding to the specified client type.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    protected function makeDriver(HttpClientConfig $config): CanHandleHttpRequest {
        $name = $config->httpClientType;
        $driverClosure = self::$drivers[$name] ?? $this->defaultDrivers()[$name] ?? null;
        if ($driverClosure === null) {
            throw new InvalidArgumentException("Client not supported: {$name}");
        }
        return $driverClosure($config, $this->events);

//        return match ($config->httpClientType) {
//            'guzzle' => new GuzzleDriver(config: $config, events: $this->events),
//            'symfony' => new SymfonyDriver(config: $config, events: $this->events),
//            'laravel' => new LaravelDriver(config: $config, events: $this->events),
//            default => throw new InvalidArgumentException("Client not supported: {$config->httpClientType}"),
//        };
    }

    /**
     * Returns the default drivers available for the HTTP client.
     *
     * @return array An associative array of default drivers with their respective configuration closures.
     */
    private function defaultDrivers() : array {
        return [
            'guzzle' => fn($config, $events) => new GuzzleDriver(config: $config, events: $events),
            'symfony' => fn($config, $events) => new SymfonyDriver(config: $config, events: $events),
            'laravel' => fn($config, $events) => new LaravelDriver(config: $config, events: $events),
        ];
    }
}
