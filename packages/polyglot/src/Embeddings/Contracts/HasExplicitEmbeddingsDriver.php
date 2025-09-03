<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;

/**
 * Contract for resolvers/providers that can supply an explicit embeddings driver.
 * Facades consult this to bypass factory construction when a custom driver
 * is provided for advanced use cases.
 */
interface HasExplicitEmbeddingsDriver
{
    /**
     * Returns an explicit embeddings driver or null if none set.
     */
    public function explicitEmbeddingsDriver(): ?CanHandleVectorization;
}
