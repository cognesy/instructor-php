<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;

/**
 * Contract for resolving Embeddings configuration from various sources.
 * Implementations handle preset resolution, DSN parsing and override merging
 * to produce a validated, immutable EmbeddingsConfig instance.
 */
interface CanResolveEmbeddingsConfig
{
    /**
     * Resolve and return a finalized EmbeddingsConfig.
     */
    public function resolveConfig(): EmbeddingsConfig;
}
