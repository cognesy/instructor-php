<?php

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use Psr\EventDispatcher\EventDispatcherInterface;

class HttpClientFactory
{
    protected HttpClientDriverFactory $driverFactory;
    protected EventDispatcherInterface $events;

    public function __construct(
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->driverFactory = new HttpClientDriverFactory($this->events);
    }

    /**
     * Gets the default config of HttpClient
     */
    public function default(): HttpClient {
        return $this->makeClient();
    }

    public function make(string $name): HttpClient {
        return $this->makeClient(preset: $name);
    }

    /**
     * Configures the HttpClient instance with the given client name.
     *
     * @param string $name The name of the client config preset to load the configuration from.
     * @return self Returns the instance of the class for method chaining.
     */
    public function fromPreset(string $name): HttpClient {
        return $this->makeClient(preset: $name);
    }

    /**
     * Configures the HttpClient instance with the given configuration.
     *
     * @param HttpClientConfig $config The configuration object to set up the HttpClient.
     * @return self Returns the instance of the class for method chaining.
     */
    public function fromConfig(HttpClientConfig $config): HttpClient {
        return $this->makeClient(config: $config);
    }

    /**
     * Sets the HTTP handler driver for the instance.
     *
     * @param CanHandleHttpRequest $driver The driver capable of handling HTTP requests.
     * @return self Returns the instance of the class for method chaining.
     */
    public function fromDriver(CanHandleHttpRequest $driver, ?object $clientInstance = null): HttpClient {
        if ($driver instanceof HttpClient) {
            return $driver;
        }
        return $this->makeClient(driver: $driver, clientInstance: $clientInstance);
    }

    // INTERNAL //////////////////////////////////////////////////////

    private function makeClient(
        string $preset = '',
        ?HttpClientConfig $config = null,
        ?CanHandleHttpRequest $driver = null,
        ?object $clientInstance = null,
        ?MiddlewareStack $stack = null,
    ): HttpClient {
        $config = $config ?? HttpClientConfig::load($preset ?: Settings::get('http', "defaultPreset"));

        $httpClient = new HttpClient(
            driver: $driver ?? $this->driverFactory->makeDriver(config: $config, clientInstance: $clientInstance),
            events: $this->events,
            stack: $stack ?? new MiddlewareStack($this->events),
        );

        $debugConfig = DebugConfig::load();
        if ($debugConfig->httpEnabled) {
            $httpClient->withDebug($debugConfig->httpEnabled);
        }

        return $httpClient;
    }
}