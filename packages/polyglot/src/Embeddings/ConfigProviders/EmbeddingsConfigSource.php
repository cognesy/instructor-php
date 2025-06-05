<?php

namespace Cognesy\Polyglot\Embeddings\ConfigProviders;

use Cognesy\Polyglot\Embeddings\Contracts\CanProvideEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Config\ConfigProviders\ConfigSource;

class EmbeddingsConfigSource extends ConfigSource implements CanProvideEmbeddingsConfig
{
    static public function default() : static {
        return (new static())->tryFrom(fn() => new SettingsEmbeddingsConfigProvider());
    }

    static public function defaultWithEmptyFallback(): static {
        return (new static())
            ->tryFrom(fn() => new SettingsEmbeddingsConfigProvider())
            ->fallbackTo(fn() => new EmbeddingsConfig())
            ->allowEmptyFallback(true);
    }

    static public function makeWith(?CanProvideEmbeddingsConfig $provider) : static {
        return (new static())
            ->tryFrom($provider)
            ->thenFrom(fn() => new SettingsEmbeddingsConfigProvider())
            ->allowEmptyFallback(false);
    }

    public function getConfig(?string $preset = ''): EmbeddingsConfig {
        return parent::getConfig($preset);
    }
}