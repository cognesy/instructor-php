<?php

namespace Cognesy\Polyglot;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientFactory;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use Psr\EventDispatcher\EventDispatcherInterface;

class Provider
{
    protected ProviderConfig $config;
    protected EventDispatcherInterface $events;
    protected CanHandleHttpRequest $httpClient;

    /**
     * Constructor for initializing dependencies and configurations.
     *
     * @param string $preset The connection preset.
     * @param ProviderConfig|null $config Configuration object.
     * @param CanHandleHttpRequest|null $httpClient HTTP client handler.
     * @param EventDispatcher|null $events Event dispatcher.
     *
     * @return void
     */
    public function __construct(
        string                $preset = '',
        ?ProviderConfig       $config = null,
        ?CanHandleHttpRequest $httpClient = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->config = $config ?? ProviderConfig::load(
            preset: $preset ?: Settings::get('providers', "defaultProvider")
        );
        $this->httpClient = $httpClient ?? (new HttpClientFactory($this->events))->fromPreset($this->config->httpClient);
    }

    // STATIC //////////////////////////////////////////////////////////////////

    /**
     * Creates a new LLM instance for the specified connection
     *
     * @param string $preset
     * @return self
     */
    public static function connection(string $preset = ''): self {
        return new self(preset: $preset);
    }

    public static function fromDSN(string $dsn): self {
        $config = ProviderConfig::fromDSN($dsn);
        return new self(config: $config);
    }

    // PUBLIC //////////////////////////////////////////////////////////////////

    /**
     * Updates the configuration and re-initializes the driver.
     *
     * @param \Cognesy\Polyglot\LLM\Data\LLMConfig $config The configuration object to set.
     *
     * @return self
     */
    public function withConfig(ProviderConfig $config): self {
        $this->config = $config;
        return $this;
    }

    /**
     * Sets the connection and updates the configuration and driver.
     *
     * @param string $preset The connection string to be used.
     *
     * @return self Returns the current instance with the updated connection.
     */
    public function using(string $preset): self {
        if (empty($preset)) {
            return $this;
        }
        $this->config = ProviderConfig::load($preset);
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
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Enable or disable debugging for the current instance.
     *
     * @param bool $debug Whether to enable debug mode. Default is true.
     *
     * @return self
     */
    public function withDebug(bool $debug = true): self {
        // TODO: needs to be solved - it only works when we're using HttpClient class as a driver
        $this->httpClient->withDebug($debug);
        return $this;
    }

    /**
     * Returns the current configuration object.
     *
     * @return ProviderConfig
     */
    public function config(): ProviderConfig {
        return $this->config;
    }
}