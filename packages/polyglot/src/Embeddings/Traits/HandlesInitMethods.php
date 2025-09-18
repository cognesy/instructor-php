<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanResolveEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

trait HandlesInitMethods
{
    public function using(string $preset) : self {
        $this->embeddingsProvider->withPreset($preset);
        return $this;
    }

    public function withPreset(string $preset) : self {
        $this->embeddingsProvider->withPreset($preset);
        return $this;
    }

    public function withDsn(string $dsn) : self {
        $this->embeddingsProvider->withDsn($dsn);
        return $this;
    }

    public function withConfig(EmbeddingsConfig $config) : self {
        $this->embeddingsProvider->withConfig($config);
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider) : self {
        $this->embeddingsProvider->withConfigProvider($configProvider);
        return $this;
    }

    public function withDriver(CanHandleVectorization $driver) : self {
        $this->embeddingsProvider->withDriver($driver);
        return $this;
    }

    public function withProvider(EmbeddingsProvider $provider) : self {
        $this->embeddingsProvider = $provider;
        return $this;
    }

    public function withEmbeddingsResolver(CanResolveEmbeddingsConfig $resolver) : self {
        $this->embeddingsResolver = $resolver;
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : self {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function withDebugPreset(?string $debug) : self {
        $this->httpDebugPreset = $debug;
        return $this;
    }
}
