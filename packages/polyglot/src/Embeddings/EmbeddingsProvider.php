<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\HttpClientFactory;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Settings;
use Psr\EventDispatcher\EventDispatcherInterface;

class EmbeddingsProvider
{
    protected EventDispatcherInterface $events;
    protected EmbeddingsConfig $config;
    protected CanHandleHttpRequest $httpClient;
    protected CanVectorize $driver;
    protected EmbeddingsDriverFactory $driverFactory;

    public function __construct(
        string                $preset = '',
        ?EmbeddingsConfig     $config = null,
        ?CanHandleHttpRequest $httpClient = null,
        ?CanVectorize         $driver = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $preset = $preset ?: Settings::get('embed', "defaultPreset");
        $this->config = $config ?? EmbeddingsConfig::load($preset);
        $this->httpClient = $httpClient ?? (new HttpClientFactory($this->events))->fromPreset($this->config->httpClient);
        $this->driverFactory = new EmbeddingsDriverFactory($this->events);
        $this->driver = $driver ?? $this->driverFactory->makeDriver($this->config, $this->httpClient);
    }

    // PUBLIC static ////////////////////////////////////////////

    public static function preset(string $preset = ''): self {
        return new self(preset: $preset);
    }

    public static function connection(string $preset = ''): self {
        return new self(preset: $preset);
    }

    public static function fromDSN(string $dsn): self {
        return new self(config: EmbeddingsConfig::fromDSN($dsn));
    }

    // PUBLIC ///////////////////////////////////////////////////

    /**
     * Configures the Embeddings instance with the given connection name.
     * @param string $preset
     * @return $this
     */
    public function using(string $preset) : self {
        $this->config = EmbeddingsConfig::load($preset);
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given DSN.
     * @param string $preset
     * @return $this
     */
    public function withDsn(string $dsn) : self {
        $this->config = EmbeddingsConfig::fromDSN($dsn);
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public function withConfig(EmbeddingsConfig $config) : self {
        $this->config = $config;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given model name.
     * @param string $model
     * @return $this
     */
    public function withModel(string $model) : self {
        $this->config->model = $model;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given HTTP client.
     *
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $httpClient
     * @return $this
     */
    public function withHttpClient(CanHandleHttpRequest $httpClient) : self {
        $this->httpClient = $httpClient;
        $this->driver = $this->driverFactory->makeDriver($this->config, $this->httpClient);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given driver.
     * @param CanVectorize $driver
     * @return $this
     */
    public function withDriver(CanVectorize $driver) : self {
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
        // TODO: it assumes we're using HttpClient class as a driver
        $this->httpClient->withDebug($debug);
        return $this;
    }

    /**
     * Retrieves the current configuration object.
     *
     * @return EmbeddingsConfig The current configuration object.
     */
    public function config() : EmbeddingsConfig {
        return $this->config;
    }

    /**
     * Retrieves the current driver instance.
     *
     * @return CanVectorize The current driver instance.
     */
    public function driver() : CanVectorize {
        return $this->driver;
    }

    /**
     * Handles the embeddings request and returns the response.
     *
     * @param EmbeddingsRequest $request The embeddings request object.
     * @return EmbeddingsResponse The embeddings response object.
     */
    public function handleEmbeddingsRequest(EmbeddingsRequest $request) : EmbeddingsResponse {
        return $this->driver->vectorize(
            $request->inputs(),
            $request->options(),
        );
    }
}