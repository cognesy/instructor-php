<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\CanResolveEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\HasExplicitEmbeddingsDriver;

final class EmbeddingsProvider implements CanResolveEmbeddingsConfig, HasExplicitEmbeddingsDriver
{
    private function __construct(
        private readonly EmbeddingsConfig $config,
        private readonly ?CanHandleVectorization $explicitDriver = null,
    ) {}

    public static function new(
        ?EmbeddingsConfig $config = null,
    ): self {
        return new self(
            config: $config ?? EmbeddingsConfig::fromArray([]),
        );
    }

    public static function fromEmbeddingsConfig(
        EmbeddingsConfig $config,
    ): self {
        return new self(
            config: $config,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self {
        return self::fromEmbeddingsConfig(EmbeddingsConfig::fromArray($config));
    }

    public function with(
        ?EmbeddingsConfig $config = null,
        ?CanHandleVectorization $explicitDriver = null,
    ): self {
        return new self(
            config: $config ?? $this->config,
            explicitDriver: $explicitDriver ?? $this->explicitDriver,
        );
    }

    public function withConfig(EmbeddingsConfig $config): self {
        return $this->with(config: $config);
    }

    public function withConfigOverrides(array $overrides): self {
        return $this->with(config: $this->config->withOverrides($overrides));
    }

    public function withDriver(CanHandleVectorization $driver): self {
        return $this->with(explicitDriver: $driver);
    }

    #[\Override]
    public function resolveConfig(): EmbeddingsConfig {
        return $this->config;
    }

    #[\Override]
    public function explicitEmbeddingsDriver(): ?CanHandleVectorization {
        return $this->explicitDriver;
    }
}
