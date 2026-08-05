<?php declare(strict_types=1);
namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;

/**
 * Defines the contract for embedding generation services.
 */
interface CanHandleVectorization
{
    public function handle(EmbeddingsRequest $request): EmbeddingsResponse;
}
