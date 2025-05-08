<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Events\InferenceRequested;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;

/**
 * This class represents an interface to Large Language Model provider APIs,
 * handling configurations, HTTP client integrations, inference drivers,
 * and event dispatching.
 */
class LLM
{
    protected LLMConfig $config;

    protected EventDispatcher $events;
    protected CanHandleHttpRequest $httpClient;
    protected CanHandleInference $driver;
    protected InferenceDriverFactory $driverFactory;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param string $connection The connection string.
     * @param \Cognesy\Polyglot\LLM\Data\LLMConfig|null $config Configuration object.
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest|null $httpClient HTTP client handler.
     * @param CanHandleInference|null $driver Inference handler.
     * @param \Cognesy\Utils\Events\EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        string                $connection = '',
        ?LLMConfig            $config = null,
        ?CanHandleHttpRequest $httpClient = null,
        ?CanHandleInference   $driver = null,
        ?EventDispatcher      $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->config = $config ?? LLMConfig::load(
            connection: $connection ?: Settings::get('llm', "defaultConnection")
        );
        $this->httpClient = $httpClient ?? HttpClient::make(client: $this->config->httpClient, events: $this->events);

        $this->driverFactory = new InferenceDriverFactory();
        $this->driver = $driver ?? $this->driverFactory->makeDriver($this->config, $this->httpClient, $this->events);
    }

    // STATIC //////////////////////////////////////////////////////////////////

    /**
     * Creates a new LLM instance for the specified connection
     *
     * @param string $connection
     * @return self
     */
    public static function connection(string $connection = ''): self {
        return new self(connection: $connection);
    }

    public static function registerDriver(string $name, string|callable $driver) : void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }

    // PUBLIC //////////////////////////////////////////////////////////////////

    /**
     * Updates the configuration and re-initializes the driver.
     *
     * @param \Cognesy\Polyglot\LLM\Data\LLMConfig $config The configuration object to set.
     *
     * @return self
     */
    public function withConfig(LLMConfig $config): self {
        $this->config = $config;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient, $this->events);
        return $this;
    }

    /**
     * Sets the connection and updates the configuration and driver.
     *
     * @param string $connection The connection string to be used.
     *
     * @return self Returns the current instance with the updated connection.
     */
    public function withConnection(string $connection): self {
        if (empty($connection)) {
            return $this;
        }
        $this->config = LLMConfig::load($connection);
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient, $this->events);
        return $this;
    }

    /**
     * Sets a custom HTTP client and updates the inference driver accordingly.
     *
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $httpClient The custom HTTP client handler.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function withHttpClient(CanHandleHttpRequest $httpClient): self {
        $this->httpClient = $httpClient;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient, $this->events);
        return $this;
    }

    /**
     * Sets the driver for inference handling and returns the current instance.
     *
     * @param CanHandleInference $driver The inference handler to be set.
     *
     * @return self
     */
    public function withDriver(CanHandleInference $driver): self {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Enable or disable debugging for the current instance.
     *
     * @param bool $debug Whether to enable debug mode. Default is true.
     *
     * @return self
     */
    public function withDebug(bool $debug = true) : self {
        $this->httpClient->withDebug($debug);
        return $this;
    }

    /**
     * Returns the current configuration object.
     *
     * @return \Cognesy\Polyglot\LLM\Data\LLMConfig
     */
    public function config() : LLMConfig {
        return $this->config;
    }

    /**
     * Returns the current inference driver.
     *
     * @return CanHandleInference
     */
    public function driver() : CanHandleInference {
        return $this->driver;
    }

    /**
     * Returns the HTTP response object for given inference request
     *
     * @param InferenceRequest $request
     * @return \Cognesy\Http\Contracts\HttpClientResponse
     */
    public function handleInferenceRequest(InferenceRequest $request) : HttpClientResponse {
        $this->events->dispatch(new InferenceRequested($request));
        return $this->driver->handle($request);
    }
}