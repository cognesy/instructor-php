<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\HttpClientFactory;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Utils\Deferred;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use Psr\EventDispatcher\EventDispatcherInterface;

class LLM
{
    protected HttpClientFactory $httpClientFactory;
    protected InferenceDriverFactory $driverFactory;

    protected LLMConfig $config;
    protected EventDispatcherInterface $events;

    protected Deferred $httpClient;
    protected Deferred $driver;
    protected bool $debug = false;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param string $preset The connection preset.
     * @param LLMConfig|null $config Configuration object.
     * @param CanHandleHttpRequest|null $httpClient HTTP client handler.
     * @param CanHandleInference|null $driver Inference handler.
     * @param EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        string $preset = '',
        ?LLMConfig $config = null,
        ?CanHandleHttpRequest $httpClient = null,
        ?CanHandleInference $driver = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->config = $config ?? LLMConfig::load(
            preset: $preset ?: Settings::get('llm', "defaultPreset")
        );
        $this->httpClientFactory = new HttpClientFactory($this->events);
        $this->httpClient = new Deferred(fn($debug) => $httpClient ?? $this->httpClientFactory->fromPreset($this->config->httpClient)->withDebug($debug));
        $this->driverFactory = new InferenceDriverFactory(events: $this->events);
        $this->driver = new Deferred(fn($debug) => $driver ?? $this->driverFactory->makeDriver($this->config, $this->httpClient->resolveUsing($debug)));
    }

    // STATIC //////////////////////////////////////////////////////////////////

    public static function preset(string $preset = ''): self {
        return new self(preset: $preset);
    }

    public static function connection(string $preset = ''): self {
        return new self(preset: $preset);
    }

    public static function fromDSN(string $dsn): self {
        $config = LLMConfig::fromDSN($dsn);
        return new self(config: $config);
    }

    public static function registerDriver(string $name, string|callable $driver) : void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }

    // PUBLIC //////////////////////////////////////////////////////////////////

    /**
     * Sets the connection and updates the configuration and driver.
     *
     * @param string $preset The connection preset to be used.
     *
     * @return self Returns the current instance with the updated connection.
     */
    public function using(string $preset): self {
        if (empty($preset)) {
            return $this;
        }
        $this->withConfig(LLMConfig::load($preset));
        return $this;
    }

    /**
     * Updates the configuration and re-initializes the driver.
     *
     * @param LLMConfig $config The configuration object to set.
     *
     * @return self
     */
    public function withConfig(LLMConfig $config): self {
        $this->config = $config;
        if (!empty($this->config->httpClient)) {
            $this->httpClient->defer(fn($debug) => $this->httpClientFactory->fromPreset($this->config->httpClient)->withDebug($debug));
        }
        $this->driver->defer(fn($debug) => $this->driverFactory->makeDriver($this->config, $this->httpClient->resolveUsing($debug)));
        return $this;
    }

    /**
     * Sets a custom HTTP client and updates the inference driver accordingly.
     *
     * @param CanHandleHttpRequest $httpClient The custom HTTP client handler.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function withHttpClient(CanHandleHttpRequest $httpClient): self {
        $this->driver->defer(fn($debug) => $this->driverFactory->makeDriver($this->config, $httpClient->withDebug($debug)));
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
        $this->driver->defer(fn() => $driver);
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
        $this->debug = $debug;
        return $this;
    }

    /**
     * Returns the current configuration object.
     *
     * @return LLMConfig
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
        return $this->driver->resolveUsing($this->debug);
    }
}