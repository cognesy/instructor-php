<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanProvideEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

trait HandlesInitMethods
{
    /**
     * Configures the Embeddings instance with the given connection name.
     * @param string $preset
     * @return $this
     */
    public function using(string $preset) : self {
        $this->embeddingsProvider->withPreset($preset);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration.
     *
     * @param \Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig $config
     * @return $this
     */
    public function withConfig(EmbeddingsConfig $config) : self {
        $this->embeddingsProvider->withConfig($config);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given configuration provider.
     *
     * @param CanProvideEmbeddingsConfig $configProvider
     * @return $this
     */
    public function withConfigProvider(CanProvideEmbeddingsConfig $configProvider) : self {
        $this->embeddingsProvider->withConfigProvider($configProvider);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given driver.
     *
     * @param CanHandleVectorization $driver
     * @return $this
     */
    public function withDriver(CanHandleVectorization $driver) : self {
        $this->embeddingsProvider->withDriver($driver);
        return $this;
    }

    public function withProvider(EmbeddingsProvider $provider) : self {
        $this->embeddingsProvider = $provider;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given HTTP client.
     *
     * @param HttpClient $httpClient
     * @return $this
     */
    public function withHttpClient(HttpClient $httpClient) : self {
        $this->embeddingsProvider->withHttpClient($httpClient);
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
        $this->embeddingsProvider->withDebug($debug);
        return $this;
    }
}