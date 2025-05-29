<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientFactory;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Deferred;
use Cognesy\Utils\Events\EventDispatcher;
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
        HttpClient               $httpClient,
        CanHandleVectorization   $driver,
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
    public static function using(string $preset) : self {
        $config = EmbeddingsConfig::load($preset);
        $events = new EventDispatcher();
        $httpClient = (new HttpClientFactory($events))->fromPreset($config->httpClient);
        $driver = (new EmbeddingsDriverFactory($events))->makeDriver(
            $config,
            $httpClient
        );
        return new self($events, $config, $httpClient, $driver);
    }

    /**
     * Configures the Embeddings instance with the given DSN.
     * @param string $preset
     * @return $this
     */
    public static function fromDsn(string $dsn) : self {
        $config = EmbeddingsConfig::fromDSN($dsn);
        $events = new EventDispatcher();
        $httpClient = (new HttpClientFactory($events))->fromPreset($config->httpClient);
        $driver = (new EmbeddingsDriverFactory($events))->makeDriver(
            $config,
            $httpClient
        );
        return new self($events, $config, $httpClient, $driver);
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public static function fromConfig(EmbeddingsConfig $config) : self {
        $events = new EventDispatcher();
        $httpClient = (new HttpClientFactory($events))->fromPreset($config->httpClient);
        $driver = (new EmbeddingsDriverFactory($events))->makeDriver(
            $config,
            $httpClient
        );
        return new self($events, $config, $httpClient, $driver);
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
     *
     * @param CanHandleVectorization $driver
     * @return $this
     */
    public function withDriver(CanHandleVectorization $driver) : self {
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
     * @return CanHandleVectorization The current driver instance.
     */
    public function driver(): CanHandleVectorization {
        return $this->driver->resolveUsing($this->debug);
    }
}