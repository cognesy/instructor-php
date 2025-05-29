<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientFactory;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Psr\EventDispatcher\EventDispatcherInterface;

class EmbeddingsProviderFactory
{
    protected EventDispatcherInterface $events;
    protected EmbeddingsDriverFactory $driverFactory;
    protected HttpClientFactory $httpClientFactory;

    public function __construct(
        EventDispatcherInterface $events,
    ) {
        $this->events = $events;
        $this->driverFactory = new EmbeddingsDriverFactory($this->events);
        $this->httpClientFactory = new HttpClientFactory($this->events);
    }

    // PUBLIC ///////////////////////////////////////////////////

    public function fromPreset(string $preset) : EmbeddingsProvider {
        $config = EmbeddingsConfig::load($preset);
        return $this->makeProvider(config: $config);
    }

    /**
     * Configures the Embeddings instance with the given DSN.
     * @param string $preset
     * @return $this
     */
    public function fromDsn(string $dsn) : EmbeddingsProvider {
        $config = EmbeddingsConfig::fromDSN($dsn);
        return $this->makeProvider(config: $config);
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public function fromConfig(EmbeddingsConfig $config) : EmbeddingsProvider {
        return $this->makeProvider(config: $config);
    }

    public function fromDriver(CanHandleVectorization $driver, ?HttpClient $httpClient = null) : EmbeddingsProvider {
        $config = EmbeddingsConfig::default();
        return $this->makeProvider(config: $config, driver: $driver, httpClient: $httpClient);
    }


    // INTERNAL ////////////////////////////////////////////////////

    private function makeProvider(
        EmbeddingsConfig        $config,
        ?HttpClient             $httpClient = null,
        ?CanHandleVectorization $driver = null,
    ) : EmbeddingsProvider {
        $client = $httpClient ?? $this->httpClientFactory->fromPreset($config->httpClient);
        $driver = $driver ?? $this->driverFactory->makeDriver($config, $client);
        return new EmbeddingsProvider(
            events: $this->events,
            config: $config,
            httpClient: $httpClient,
            driver: $driver,
        );
    }
}