<?php

namespace Cognesy\Polyglot\Http;

use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Drivers\GuzzleDriver;
use Cognesy\Polyglot\Http\Drivers\LaravelDriver;
use Cognesy\Polyglot\Http\Drivers\SymfonyDriver;
use Cognesy\Polyglot\Http\Enums\HttpClientType;
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
class HttpClient
{
    protected EventDispatcher $events;
    protected CanHandleHttp $driver;

    /**
     * Constructor method for initializing the HTTP client.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return void
     */
    public function __construct(string $client = '', EventDispatcher $events = null) {
        $this->events = $events ?? new EventDispatcher();
        $config = HttpClientConfig::load($client ?: Settings::get('http', "defaultClient"));
        $this->driver = $this->makeDriver($config);
    }

    /**
     * Static factory method to create an instance of the HTTP handler.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return \Cognesy\Polyglot\Http\Contracts\CanHandleHttp Returns an instance that can handle HTTP operations.
     */
    public static function make(string $client = '', ?EventDispatcher $events = null) : CanHandleHttp {
        return (new self($client, $events))->get();
    }

    /**
     * Configures the HttpClient instance with the given client name.
     *
     * @param string $name The name of the client to load the configuration for.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withClient(string $name) : self {
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
    public function withConfig(HttpClientConfig $config) : self {
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    /**
     * Sets the HTTP handler driver for the instance.
     *
     * @param \Cognesy\Polyglot\Http\Contracts\CanHandleHttp $driver The driver capable of handling HTTP requests.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withDriver(CanHandleHttp $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Retrieves the current HTTP handler instance.
     *
     * @return \Cognesy\Polyglot\Http\Contracts\CanHandleHttp The HTTP handler associated with the current context.
     */
    public function get() : CanHandleHttp {
        return $this->driver;
    }

    // INTERNAL ///////////////////////////////////////////////////////

    /**
     * Creates an HTTP driver instance based on the specified configuration.
     *
     * @param HttpClientConfig $config The configuration object defining the type of HTTP client and its settings.
     * @return CanHandleHttp The instantiated HTTP driver corresponding to the specified client type.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    private function makeDriver(HttpClientConfig $config) : CanHandleHttp {
        return match ($config->httpClientType) {
            HttpClientType::Guzzle => new GuzzleDriver(config: $config, events: $this->events),
            HttpClientType::Symfony => new SymfonyDriver(config: $config, events: $this->events),
            httpClientType::Laravel => new LaravelDriver(config: $config, events: $this->events),
            default => throw new InvalidArgumentException("Client not supported: {$config->httpClientType->value}"),
        };
    }
}