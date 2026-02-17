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
    abstract protected function invalidateRuntimeCache(): void;

    protected function cloneWithEmbeddingsProvider(): static {
        $copy = clone $this;
        $copy->embeddingsProvider = clone $this->embeddingsProvider;
        return $copy;
    }

    public function using(string $preset) : static {
        $copy = $this->cloneWithEmbeddingsProvider();
        $copy->embeddingsProvider->withPreset($preset);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withDsn(string $dsn) : static {
        $copy = $this->cloneWithEmbeddingsProvider();
        $copy->embeddingsProvider->withDsn($dsn);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withConfig(EmbeddingsConfig $config) : static {
        $copy = $this->cloneWithEmbeddingsProvider();
        $copy->embeddingsProvider->withConfig($config);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withConfigProvider(CanProvideConfig $configProvider) : static {
        $copy = $this->cloneWithEmbeddingsProvider();
        $copy->embeddingsProvider->withConfigProvider($configProvider);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withDriver(CanHandleVectorization $driver) : static {
        $copy = $this->cloneWithEmbeddingsProvider();
        $copy->embeddingsProvider->withDriver($driver);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withProvider(EmbeddingsProvider $provider) : static {
        $copy = clone $this;
        $copy->embeddingsProvider = $provider;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withEmbeddingsResolver(CanResolveEmbeddingsConfig $resolver) : static {
        $copy = clone $this;
        $copy->embeddingsResolver = $resolver;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $copy = clone $this;
        $copy->httpClient = $httpClient;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    /**
     * Set HTTP debug preset explicitly.
     */
    public function withHttpDebugPreset(?string $preset) : static {
        $copy = clone $this;
        $copy->httpDebugPreset = $preset;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    /**
     * Convenience toggle for HTTP debugging.
     */
    public function withHttpDebug(bool $enabled = true) : static {
        $preset = match ($enabled) {
            true => 'on',
            false => 'off',
        };
        return $this->withHttpDebugPreset($preset);
    }
}
