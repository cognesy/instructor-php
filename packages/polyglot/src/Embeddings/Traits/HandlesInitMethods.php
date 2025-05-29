<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

trait HandlesInitMethods
{
    /**
     * Configures the Embeddings instance with the given connection name.
     * @param string $preset
     * @return $this
     */
    public function using(string $preset) : self {
        $this->provider = $this->embeddingsProviderFactory->fromPreset($preset);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     * @param EmbeddingsConfig $config
     * @return $this
     */
    public function withConfig(EmbeddingsConfig $config) : self {
        $this->provider = $this->embeddingsProviderFactory->fromConfig($config);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given driver.
     *
     * @param CanHandleVectorization $driver
     * @return $this
     */
    public function withDriver(CanHandleVectorization $driver) : self {
        $this->provider = $this->embeddingsProviderFactory->fromDriver($driver);
        return $this;
    }

    public function withProvider(EmbeddingsProvider $provider) : self {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given HTTP client.
     *
     * @param HttpClient $httpClient
     * @return $this
     */
    public function withHttpClient(HttpClient $httpClient) : self {
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
    public function withDebug(bool $debug = true) : self {
        $this->provider->withDebug($debug);
        return $this;
    }

    /**
     * Returns the config object for the current instance.
     *
     * @return EmbeddingsConfig The config object for the current instance.
     */
    public function config() : EmbeddingsConfig {
        return $this->provider->config();
    }

    /**
     * Returns the driver object for the current instance.
     *
     * @return CanHandleVectorization The driver object for the current instance.
     */
    public function driver(): CanHandleVectorization {
        return $this->provider->driver();
    }
}