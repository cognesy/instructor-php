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
    public function using(string $preset) : static {
        $this->embeddingsProvider->withPreset($preset);
        return $this;
    }

    public function withDsn(string $dsn) : static {
        $this->embeddingsProvider->withDsn($dsn);
        return $this;
    }

    public function withConfig(EmbeddingsConfig $config) : static {
        $this->embeddingsProvider->withConfig($config);
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider) : static {
        $this->embeddingsProvider->withConfigProvider($configProvider);
        return $this;
    }

    public function withDriver(CanHandleVectorization $driver) : static {
        $this->embeddingsProvider->withDriver($driver);
        return $this;
    }

    public function withProvider(EmbeddingsProvider $provider) : static {
        $this->embeddingsProvider = $provider;
        return $this;
    }

    public function withEmbeddingsResolver(CanResolveEmbeddingsConfig $resolver) : static {
        $this->embeddingsResolver = $resolver;
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Set HTTP debug preset explicitly (clearer than withDebugPreset()).
     */
    public function withHttpDebugPreset(?string $preset) : static {
        $this->httpDebugPreset = $preset;
        return $this;
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

    /**
     * Backward-compatible alias for HTTP debug presets.
     */
    public function withDebugPreset(?string $debug) : static {
        return $this->withHttpDebugPreset($debug);
    }
}
