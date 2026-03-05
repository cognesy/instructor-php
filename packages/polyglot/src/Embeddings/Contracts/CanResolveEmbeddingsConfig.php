<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;

/**
 * Contract for resolving finalized embeddings configuration objects.
 * Implementations return validated, immutable EmbeddingsConfig instances
 * regardless of how data was obtained at the application edge.
 */
interface CanResolveEmbeddingsConfig
{
    /**
     * Resolve and return a finalized EmbeddingsConfig.
     */
    public function resolveConfig(): EmbeddingsConfig;
}
