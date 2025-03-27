<?php
namespace Cognesy\Polyglot\Http;

use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Contracts\HttpMiddleware;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Http\Drivers\GuzzleDriver;
use Cognesy\Polyglot\Http\Drivers\LaravelDriver;
use Cognesy\Polyglot\Http\Drivers\SymfonyDriver;
use Cognesy\Polyglot\Http\Enums\HttpClientType;
use Cognesy\Polyglot\Http\Middleware\BufferResponse\BufferResponseMiddleware;
use Cognesy\Polyglot\Http\Middleware\Debug\DebugMiddleware;
use Cognesy\Polyglot\Http\Middleware\StreamByLine\StreamByLineMiddleware;
use Cognesy\Utils\Debug\Debug;
use Cognesy\Utils\Debug\DebugConfig;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

/**
 * The HttpClient class is responsible for managing HTTP client configurations and instantiating
 * appropriate HTTP driver implementations based on the provided configuration.
 *
 * @property EventDispatcher $events  Instance for dispatching events.
 * @property CanHandleHttp $driver    Instance that handles HTTP requests.
 */
class HttpClient implements CanHandleHttp
{
    protected EventDispatcher $events;
    protected CanHandleHttp $driver;
    protected MiddlewareStack $stack;

    /**
     * Constructor method for initializing the HTTP client.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return void
     */
    public function __construct(string $client = '', EventDispatcher $events = null) {
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
     * @return CanHandleHttp Returns an instance that can handle HTTP operations.
     */
    public static function make(string $client = '', ?EventDispatcher $events = null): CanHandleHttp {
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
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    /**
     * Configures the HttpClient instance with the given configuration.
     *
     * @param HttpClientConfig $config The configuration object to set up the HttpClient.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withConfig(HttpClientConfig $config): self {
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    /**
     * Sets the HTTP handler driver for the instance.
     *
     * @param CanHandleHttp $driver The driver capable of handling HTTP requests.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withDriver(CanHandleHttp $driver): self {
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
            $this->stack->append(new DebugMiddleware(
                new Debug(new DebugConfig(httpEnabled: true)), $this->events),
                'internal:debug'
            );
            $this->stack->append(new BufferResponseMiddleware(), 'internal:buffering');
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

    // INTERNAL ///////////////////////////////////////////////////////

    /**
     * Creates an HTTP driver instance based on the specified configuration.
     *
     * @param HttpClientConfig $config The configuration object defining the type of HTTP client and its settings.
     * @return CanHandleHttp The instantiated HTTP driver corresponding to the specified client type.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    private function makeDriver(HttpClientConfig $config): CanHandleHttp {
        return match ($config->httpClientType) {
            HttpClientType::Guzzle->value => new GuzzleDriver(config: $config, events: $this->events),
            HttpClientType::Symfony->value => new SymfonyDriver(config: $config, events: $this->events),
            httpClientType::Laravel->value => new LaravelDriver(config: $config, events: $this->events),
            default => throw new InvalidArgumentException("Client not supported: {$config->httpClientType}"),
        };
    }
}
