<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientFactory;
use Cognesy\Polyglot\Embeddings\Contracts\CanVectorize;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Deferred;
use Psr\EventDispatcher\EventDispatcherInterface;

class EmbeddingsProvider
{
    protected EventDispatcherInterface $events;
    protected EmbeddingsConfig $config;

    protected Deferred $httpClient;
    protected Deferred $driver;

    protected EmbeddingsDriverFactory $driverFactory;
    protected HttpClientFactory $httpClientFactory;

    protected bool $debug = false;

    public function __construct(
        EventDispatcherInterface $events,
        EmbeddingsConfig         $config,
        HttpClient      $httpClient,
        CanVectorize             $driver,
    ) {
        $this->events = $events;
        $this->config = $config;

        $this->driverFactory = new EmbeddingsDriverFactory($this->events);
        $this->httpClientFactory = new HttpClientFactory($this->events);

        $this->httpClient = new Deferred(fn($debug) => $httpClient ?? $this->httpClientFactory->fromPreset($this->config->httpClient)->withDebug($debug));
        $this->driver = new Deferred(fn($debug) => $driver ?? $this->driverFactory->makeDriver(
            $this->config,
            $this->httpClient->resolveUsing($debug),
        ));
    }

    // PUBLIC ///////////////////////////////////////////////////

    /**
     * Configures the Embeddings instance with the given connection name.
     * @param string $preset
     * @return $this
     */
    public function using(string $preset) : self {
        $this->config = EmbeddingsConfig::load($preset);
        $this->driver->defer(fn($debug) => $this->driverFactory->makeDriver(
            $this->config,
            $this->httpClient->resolveUsing($debug)
        ));
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given DSN.
     * @param string $preset
     * @return $this
     */
    public function withDsn(string $dsn) : self {
        $this->config = EmbeddingsConfig::fromDSN($dsn);
        $this->driver->defer(fn($debug) => $this->driverFactory->makeDriver(
            $this->config,
            $this->httpClient->resolveUsing($debug)
        ));
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public function withConfig(EmbeddingsConfig $config) : self {
        $this->config = $config;
        $this->driver->defer(fn($debug) => $this->driverFactory->makeDriver(
            $this->config,
            $this->httpClient->resolveUsing($debug)
        ));
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
     * @param HttpClient $httpClient
     * @return $this
     */
    public function withHttpClient(HttpClient $httpClient) : self {
        $this->httpClient->defer(fn($debug) => $httpClient->withDebug($debug));
        $this->driver->defer(fn($debug) => $this->driverFactory->makeDriver(
            $this->config,
            $this->httpClient->resolveUsing($debug)
        ));
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given driver.
     * @param CanVectorize $driver
     * @return $this
     */
    public function withDriver(CanVectorize $driver) : self {
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
        return $this->driver->resolveUsing($this->debug);
    }
}