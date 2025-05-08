<?php
namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\EmbeddingsResponse;

/**
 * Interface CanVectorize
 *
 * Defines the contract for embedding generation services
 */
interface CanVectorize
{
    /**
     * Generate embeddings for the input
     *
     * @param array<string> $input
     * @param array $options
     * @return EmbeddingsResponse
     */
    public function vectorize(array $input, array $options = []) : EmbeddingsResponse;
}
