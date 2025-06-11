<?php
namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;

/**
 * Interface CanVectorize
 *
 * Defines the contract for embedding generation services
 */
interface CanHandleVectorization
{
    /**
     * Generate embeddings for the input
     *
     * @param array<string> $input
     * @param array $options
     * @return \Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse
     */
    public function handle(EmbeddingsRequest $request) : HttpClientResponse;
    public function fromData(array $data): ?EmbeddingsResponse;
}
